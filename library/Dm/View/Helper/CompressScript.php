<?php

/**
 * Helper for pooling all JS scripts to one cached file
 * For minifying script file JSMin library is used
 *
 * Prototype for this helper comes from here
 * http://habrahabr.ru/blogs/zend_framework/85324/
 *
 * But there were some great problems with given code:
 * - no testing facilities cause of using $_SERVER array
 * - hard to change rules for folders mapping when additional domains/CDN are used
 * - several bags with static variables using
 * - many code repeats
 * - etc...
 * So, I rewrite most code of this helper to make it more flexible and stable,
 * but great thanks previous author for idea and first steps!
 *
 * @package    View
 * @subpackage Helper
 * @version    0.3.1
 * @author     Alexey S. Kachayev <kachayev@gmail.com>
 * @link       https://github.com/kachayev/zend-fw-head-compressor
 */
class Dm_View_Helper_CompressScript
    extends Zend_View_Helper_HeadScript
{
    /**
     * @var string
     */
    protected $_defaultCacheDir = '/cache/js';

    /**
     * Object for file processing
     *
     * @var Dm_View_Helper_Head_File
     */
    protected $_processor = null;

    /**
     * Processing helper
     *
     * Compress files using toString convertion
     * or just return headScript helpers result if combine options set to FALSE
     *
     * @param  array|Zend_Config|null $config
     * @return string
     */
    public function compressScript($config=null)
    {
        if ($config instanceof Zend_Config) {
            $config = $config->toArray();
        } elseif (!is_array($config)) {
            $config = array();
        }

        // Merge default configuration
        $config = array_merge(array('dir'=>$this->_defaultCacheDir,'extenstion'=>'js'),$config);
        $this->setConfig($config);

        return $this->getOption('combine', true) ? $this->toString() : $this->view->headScript();
    }

    /**
     * Retrieve string representation
     *
     * @param  string|int $indent
     * @return string
     */
    public function toString($indent = null)
    {
        $headScript = $this->view->headScript();
        $indent     = is_null($indent) ? $headScript->getIndent() : $headScript->getWhitespace($indent);
        $useCdata   = ($this->view) ? $this->view->doctype()->isXhtml() : (bool) $headScript->useCdata;

        list($escapeStart, $escapeEnd) = ($useCdata) ? array('//<![CDATA[','//]]>') : array('//<!--','//-->');

        $items = array();
        $headScript->getContainer()->ksort();
        foreach ($headScript as $item) {
            if (!$headScript->_isValid($item)) {
                // Pass item adding for invalid one
                continue;
            }

            // Check if this item cachable and create specific container for it if necessary
            if (!$this->getProcessor()->isCachable($item)) {
                $items[] = $this->itemToString($item, $indent, $escapeStart, $escapeEnd);
            } else {
                $this->getProcessor()->cache($item);
            }
        }

        // Add to items list HTML container with compiled items
        array_unshift($items, $this->itemToString($this->_getCompiledItem(), $indent, $escapeStart, $escapeEnd));

        // Return string representaion for all HTML containers
        return implode($headScript->getSeparator(), $items);
    }

    /**
     * Build SCRIPT conteiner for given item (HTML reprsentation)
     *
     * @param  string $item
     * @param  string $indent
     * @param  string $escapeStart
     * @param  string $escapeEnd
     * @return string 
     */
    public function itemToString($item, $indent, $escapeStart, $escapeEnd)
    {
        $attrString = '';
        if (!empty($item->attributes)) {
            foreach ($item->attributes as $key => $value) {
                if (!$this->arbitraryAttributesAllowed()
                        && !in_array($key, $this->_optionalAttributes)) {
                    continue;
                }
                
                if ('defer' == $key) {
                    $value = 'defer';
                }
                
                $attrString .= sprintf(' %s="%s"', $key,
                                       ($this->_autoEscape) ? $this->_escape($value) : $value);
            }
        }

        $type = ($this->_autoEscape) ? $this->_escape($item->type) : $item->type;
        $html = $indent . '<script type="' . $type . '"' . $attrString . '>';
        
        if (!empty($item->source)) {
            $html = implode(PHP_EOL, array($html,
                                           $indent . '  ' . $escapeStart,
                                           $item->source . $indent . '  ' . $escapeEnd,
                                           $indent));
        }
        
        $html .= '</script>';

        if (!empty($item->attributes['conditional']) && is_string($item->attributes['conditional'])) {
            $html = '<!--[if ' . $item->attributes['conditional'] . ']> ' . $html . '<![endif]-->';
        }

        return $html;
    }


    /**
     * Compile full list of files in $this->_cache array
     *
     * @return string
     */
    protected function _getCompiledItem()
    {
        $fileProcessor = $this->getProcessor();
        $path = $fileProcessor->getServerPath(
            $this->getOption('dir') . $fileProcessor->fullFilename(md5(serialize($fileProcessor->getCache())))
        );
        
        if (!file_exists($path)) {
            // Check if necessary directory is exists
            $dir = dirname($path);
            if (!is_dir($dir) && !@mkdir($dir, 0777, true)) {
                // @todo: Use more specific exception here
                throw new Zend_View_Exception('Impossible to create destination directory ' . $dir);
            }

            // Build file content
            $jsContent = '';
            foreach ($fileProcessor->getCache() as $js) {
                $jsContent .= (is_array($js) ? file_get_contents($js['filepath']) : $js) . "\n\n";
            }

            // Minify by using 3rd-party library
            if ($this->getOption('compress')) {
                require_once 'Tools/JSMin.php';
                $jsContent = JSMin::minify($jsContent);
            }

            // Write js content to file
            file_put_contents($path, $jsContent);
        }
        
        return $this->createData('text/javascript', array('src' => $fileProcessor->getWebPath($path)));
    }

    /**
     * Set configuration, possible to use array or Zend_Config object
     *
     * @param  array|Zend_Config $config
     * @return null
     */
    public function setConfig($config=null)
    {
        return $this->getProcessor()->setConfig($config);
    }

    /**
     * Return option from current object configuration
     *
     * @param  string $name
     * @param  mixed  $defaultValue
     * @return mixed
     */
    public function getOption($name, $defaultValue=null)
    {
        return $this->getProcessor()->getOption($name, $defaultValue);
    }

    /**
     * Create file processing object or return existen
     *
     * @return Dm_View_Helper_Head_File
     */
    public function getProcessor() {
        if(null === $this->_processor) {
            $this->setProcessor(new Dm_View_Helper_Head_File());
        }

        return $this->_processor;
    }

    /**
     * Set file processor object using FileAbstract for dependency keeping
     *
     * @param  Dm_View_Helper_Head_FileAbstract $processor
     * @return $this
     */
    public function setProcessor(Dm_View_Helper_Head_FileAbstract $processor)
    {
        $this->_processor = $processor;
        return $this;
    }
}