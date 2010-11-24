<?php

// Ensure library/Tools/Minify is on include_path
set_include_path(implode(PATH_SEPARATOR, array(
    realpath(APPLICATION_PATH . '/../library/Tools/'),
    realpath(APPLICATION_PATH . '/../library/Tools/Minify/'),
    get_include_path(),
)));

/**
 * Helper for pooling all CSS file to one cached file
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
 * So, I rewrite full code of this helper to make it more flexible and stable,
 * but great thanks previous author for idea and first steps!
 *
 * @package    View
 * @subpackage Helper
 * @version    0.4.7
 * @author     Alex S. Kachayev <kachayev@gmail.com>
 * @link       https://github.com/kachayev/zend-fw-head-compressor
 */
class Dm_View_Helper_CompressStyle
    extends Zend_View_Helper_HeadLink
{
    /**
     * Used as suffix for compessed CSS files
     *
     * @const string
     */
    const COMPRESSED_FILE_SUFFIX = '_compressed';

    /**
     * Configuration for compressor working
     *
     * @var array
     */
    protected $_config = array(
        'dir'      => '/cache/css/',
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
            if (!$this->isCachable($item)) {
                $items[] = $this->itemToString($item);
            } else {
                $this->cache($item);
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
     * Check if CSS file/source is cachable
     *
     * @param  array|stdClass  $attributes
     * @return boolen
     */
    public function isCachable($attributes)
    {
        // For conviency works only with array format
        $attributes = (array) $attributes;

        // Check, that conditional stylesheet isn`t a string (empty or array)
        if (!empty($attributes['conditionalStylesheet']) && is_string($attributes['conditionalStylesheet'])) {
            return false;
        }

        // @todo: Add here symlinks lookign for
        return (isset($attributes['href']) && is_readable($this->_getServerPath($attributes['href'])));
    }

    /**
     * Add given item (by source or file path) to caching queue
     *
     * @param  array|stdClass $attributes
     * @return null
     */
    public function cache($attributes)
    {
        $attributes = (array) $attributes;
        $filePath   = $this->_getServerPath($attributes['href']);

        $this->_cache[] = array(
            'filepath' => $filePath,
            'mtime'    => filemtime($filePath)
        );
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

            $cssContent = '';
            foreach ($this->_cache as $css) {
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

        return $this->createDataStylesheet(array('href' => $this->_getWebPath($path)));
    }

    /**
     * Build full filename by ending it with CSS extension and IS_COMPRESSED suffix
     *
     * @param  string $filename
     * @return string
     */
    protected function _fullFilename($filename)
    {
        return $filename . ($this->getOption('compress') ? self::COMPRESSED_FILE_SUFFIX : '') . '.css';
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