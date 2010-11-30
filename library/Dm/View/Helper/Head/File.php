<?php

/**
 * Processor for handling operation with head files (js, css etc)
 *
 * @package    View
 * @subpackage Helper
 * @version    0.0.1
 * @author     Alexey S. Kachayev <kachayev@gmail.com>
 * @link       https://github.com/kachayev/zend-fw-head-compressor
 */
class Dm_View_Helper_Head_File
    extends Dm_View_Helper_Head_FileAbstract
{
    /**
     * Used as suffix for compessed files
     *
     * @const string
     */
    const COMPRESSED_FILE_SUFFIX = '_compressed';

    /**
     * Add this comment to file (or source) to prevent caching it
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
        'dir'      => '',
        'extension'=> '',
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
     * @param  array|Zend_Config|null $config
     * @return null
     */
    public function  __construct($config=null) {
        $this->setConfig($config);
    }

    /**
     * Return array of file for caching
     *
     * @return array
     */
    public function getCache()
    {
        return $this->_cache;
    }

    /**
     * Try to find JS file for processing
     * On success return full filepath, and NULL for failure
     *
     * @param  string      $src
     * @return string|null
     */
    public function searchFile($src)
    {
        $path = $this->getServerPath($src);
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
     * Check if file/source is cachable
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

        // Cache file by file, found by SRC attribute
        return (isset($item->attributes['src']) && $this->searchFile($item->attributes['src']));
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
     * Build full filename by ending it with JS extension and IS_COMPRESSED suffix
     *
     * @param  string $filename
     * @return string
     */
    public function fullFilename($filename)
    {
        return $filename . ($this->getOption('compress') 
                ? self::COMPRESSED_FILE_SUFFIX
                : '') . '.' . $this->getOption('extension');
    }

    /**
     * Build web path from server variant
     *
     * @todo: Allow overwrite this function but setting to cache getWebPath handler
     *
     * @param  string $path
     * @return string
     */
    public function getWebPath($path)
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
    public function getServerPath($path)
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
    public function setConfig($config=null)
    {
        if ($config instanceof Zend_Config) {
            $config = $config->toArray();
        } elseif(is_null($config)) {
            $config = array();
        }

        $this->_config = array_merge($this->_config, $config);
        print_r($this->_config);
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