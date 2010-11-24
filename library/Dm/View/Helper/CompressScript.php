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
     * Used as suffix for compessed JS files
     *
     * @const string
     */
    const COMPRESSED_FILE_SUFFIX = '_compressed';

    /**
     * Add this comment to JS file (or source) to prevent caching it
     *
     * @const string
     */
    const NON_CACHE_COMMENT = '//@non-cache';

    /**
     * Configuration for compressor working
     *
     * @var array
     */
    protected $_config = array(
        'dir'      => '/cache/js/',
        'combine'  => true,
        'compress' => true,
        'symlinks' => array()
    );

    /**
     * List of added files
     *
     * @var array
     */
    protected $_cache = array();

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
        if (null !== $config) {
            $this->setConfig($config);
        }

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
            if (!$this->isCachable($item)) {
                $items[] = $this->itemToString($item, $indent, $escapeStart, $escapeEnd);
            } else {
                $this->cache($item);
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
     * Try to find JS file for processing
     * On success return full filepath, and NULL for failure
     *
     * @param  string      $src
     * @return string|null
     */
    public function searchJsFile($src)
    {
        $path = $this->_getServerPath($src);
        // If this path is readable file return it
        if (is_readable($path)) {
            return $path;
        }

        // If file is not readable, look throught all symlinks
        foreach ($this->getOption('symlinks',array()) as $virtualPath => $realPath) {
            $path = str_replace($virtualPath, $realPath, '/' . ltrim($src, '/'));
            if (is_readable($path)) {
                return $path;
            }
        }

        // Return null if file not found
        return null;
    }

    /**
     * Check if JS file/source is cachable
     *
     * @param  array  $item
     * @return boolen
     */
    public function isCachable($item)
    {
        // Don't cache items with conditional attributes
        if (!empty($item->attributes['conditional']) && is_string($item->attributes['conditional'])) {
            return false;
        }

        // Don't cache items with specail comment in source
        if (!empty($item->source) && false === strpos($item->source, self::NON_CACHE_COMMENT)) {
            return true;
        }

        // Cache file by JS file, found by SRC attribute
        return (isset($item->attributes['src']) && $this->searchJsFile($item->attributes['src']));
    }

    /**
     * Add given item (by source or file path) to caching queue
     * 
     * @param  array $item
     * @return null
     */
    public function cache($item)
    {
        if (!empty($item->source)) {
            $this->_cache[] = $item->source;
        } else {
            $path = $this->searchJsFile($item->attributes['src']);
            $this->_cache[] = array(
                'filepath' => $path,
                'mtime'    => filemtime($path)
            );
        }
    }
    
    /**
     * Compile full list of files in $this->_cache array
     *
     * @return string
     */
    protected function _getCompiledItem()
    {
        $path = $this->_getServerPath($this->getOption('dir').$this->_fullFilename(md5(serialize($this->_cache))));
        if (!file_exists($path)) {
            // Check if necessary directory is exists
            $dir = dirname($path);
            if (!is_dir($dir) && !@mkdir($dir, 0777, true)) {
                // @todo: Use more specific exception here
                throw new Zend_View_Exception('Impossible to create destination directory ' . $dir);
            }

            // Build file content
            $jsContent = '';
            foreach ($this->_cache as $js) {
                $jsContent .= (is_array($js) ? file_get_contents($js['filepath']) : $js) . "\n\n";
            }

            // Minify by using 3th-party library
            if ($this->getOption('compress')) {
                require_once 'Tools/JSMin.php';
                $jsContent = JSMin::minify($jsContent);
            }

            // Write js content to file
            file_put_contents($path, $jsContent);
        }
        
        return $this->createData('text/javascript', array('src' => $this->_getWebPath($path)));
    }

    /**
     * Build full filename by ending it with JS extension and IS_COMPRESSED suffix
     *
     * @param  string $filename
     * @return string
     */
    protected function _fullFilename($filename)
    {
        return $filename . ($this->getOption('compress') ? self::COMPRESSED_FILE_SUFFIX : '') . '.js';
    }

    /**
     * Build web path from server variant
     *
     * @todo: Allow overwrite this function but setting to cache getWebPath handler
     *
     * @param  string $path
     * @return string
     */
    protected function _getWebPath($path)
    {
        return '/' . ltrim(str_replace($this->_getServerPath(''), '', $path), '/');
    }

    /**
     * Build server path from relative one
     *
     * @todo: Allow overwrite this function but setting to cache getServerPath handler
     *
     * @param  string $path
     * @return string
     */
    protected function _getServerPath($path)
    {
        $baseDir = empty($_SERVER['DOCUMENT_ROOT'])
                    ? APPLICATION_PATH . '/../public' : rtrim($_SERVER['DOCUMENT_ROOT'], '/');
        return $baseDir . '/' . ltrim($path, '/');
    }

    /**
     * Set configuration, possible to use array or Zend_Config object
     *
     * @param  array|Zend_Config $config
     * @return null
     */
    public function setConfig($config)
    {
        if ($config instanceof Zend_Config) {
            $config = $config->toArray();
        }

        $this->_config = array_merge($this->_config, $config);
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
        return array_key_exists($name, $this->_config) ? $this->_config[$name] : $defaultValue;
    }
}