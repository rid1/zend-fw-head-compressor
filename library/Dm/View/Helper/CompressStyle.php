<?php

// Ensure library/Tools/Minify is on include_path
set_include_path(implode(PATH_SEPARATOR, array(
    realpath(APPLICATION_PATH . '/../library/Tools/'),
    realpath(APPLICATION_PATH . '/../library/Tools/Minify/'),
    get_include_path(),
)));

/**
 * Helper for pooling all CSS file to one cached file
 * For minifying script file CSS compressor library is used
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
 * So, I rewrite full code of this helper to make it more flexible and stable,
 * but great thanks previous author for idea and first steps!
 *
 * @package    View
 * @subpackage Helper
 * @version    0.4.9
 * @author     Alex S. Kachayev <kachayev@gmail.com>
 * @link       https://github.com/kachayev/zend-fw-head-compressor
 */
class Dm_View_Helper_CompressStyle
    extends Zend_View_Helper_HeadLink
{
    /**
     * @var string
     */
    protected $_defaultCacheDir = '/cache/css/';

    /**
     * Object for file processing
     *
     * @var Dm_View_Helper_Head_File
     */
    protected $_processor = null;

    /**
     * Constructor
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->setConfig();
    }
    
    /**
     * Processing helper
     *
     * Compress files using toString convertion
     * or just return headLink helpers result if combine options set to FALSE
     *
     * @param  array|Zend_Config|null $config
     * @return string
     */
    public function compressStyle($config=null)
    {
        if (null !== $config) {
            $this->setConfig($config);
        }

        return $this->getOption('combine', true) ? $this->toString() : $this->view->headLink();
    }
    
    /**
     * Retrieve string representation
     *
     * @param  string|int $indent
     * @return string
     */
    public function toString($indent = null)
    {
        $headLink = $this->view->headLink();
        $indent   = (null !== $indent) ? $headLink->getWhitespace($indent) : $headLink->getIndent();
        $items    = array();
        
        $headLink->getContainer()->ksort();
        foreach ($headLink as $item) {
            if (!$headLink->_isValid($item)) {
                // Pass item adding for invalid one
                continue;
            }
            
            // Check if this item cachable and create specific container for it if necessary
            if (!$this->getProcessor()->isCachable($item)) {
                $items[] = $this->itemToString($item);
            } else {
                $this->getProcessor()->cache($item);
            }
        }

        // Add to items list HTML container with compiled items
        array_unshift($items, $this->itemToString($this->_getCompiledItem()));
        return implode($headLink->getSeparator(), $items);
    }

    /**
     * Build STYLE conteiner for given item (HTML reprsentation)
     *
     * @param  stdClass $item
     * @return string
     */
    public function itemToString(stdClass $item)
    {
        $attributes = (array) $item;
        $link = '<link ';

        foreach ($this->_itemKeys as $itemKey) {
            if (isset($attributes[$itemKey])) {
                if (is_array($attributes[$itemKey])) {
                    foreach ($attributes[$itemKey] as $key => $value) {
                        $link .= sprintf('%s="%s" ', $key, 
                                         ($this->_autoEscape) ? $this->_escape($value) : $value);
                    }
                } else {
                    $link .= sprintf('%s="%s" ', $itemKey, 
                                     ($this->_autoEscape) 
                                        ? $this->_escape($attributes[$itemKey])
                                        : $attributes[$itemKey]);
                }
            }
        }

        $link .= ($this->view instanceof Zend_View_Abstract)
                    ? ( $this->view->doctype()->isXhtml()) ? '/>' : '>'
                    : '/>';

        if (($link == '<link />') || ($link == '<link >')) {
            return '';
        }

        if (!empty($attributes['conditionalStylesheet']) && is_string($attributes['conditionalStylesheet'])) {
            $link = '<!--[if ' . $attributes['conditionalStylesheet'] . ']> ' . $link . '<![endif]-->';
        }

        return $link;
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

            $cssContent = '';
            foreach ($fileProcessor->getCache() as $css) {
                $content = file_get_contents($css['filepath']);

                require_once 'CSS.php';

                // Minify by using 3rd-party library
                $cssContent .= $this->getOption('compress', true)
                        ?  Minify_CSS::minify(
                            $content,
                            array(
                                'prependRelativePath' => dirname($path),
                                'currentDir' => dirname($css['filepath']),
                                'symlinks' => $this->getOption('symlinks')
                            )
                        )
                        : Minify_CSS_UriRewriter::rewrite(
                            $content, dirname($css['filepath']),
                            $this->_getCompiledItem(''), $this->getOption('symlinks')
                        );
                $cssContent .= "\n\n";
            }
            
            // Write css content to cache file
            file_put_contents($path, $cssContent);
        }

        return $this->createDataStylesheet(array('href' => $this->getProcessor()->getWebPath($path)));
    }

    /**
     * Set configuration, possible to use array or Zend_Config object
     *
     * @param  array|Zend_Config $config
     * @return null
     */
    public function setConfig($config=null)
    {
        if ($config instanceof Zend_Config) {
            $config = $config->toArray();
        } elseif (!is_array($config)) {
            $config = array();
        }

        // Merge default configuration
        $config = array_merge(array('dir'=>$this->_defaultCacheDir,'extension'=>'css'),$config);
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