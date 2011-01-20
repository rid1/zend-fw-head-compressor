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
     * RFC 1950, http://www.faqs.org/rfcs/rfc1950.html
     * See more http://ua2.php.net/gzcompress
     * 
     * @const string
     */
    const GZIP_COMPRESS_HEADER = "\x1f\x8b\x08\x00\x00\x00\x00\x00";

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
     * List of added files
     *
     * @var array
     */
    protected $_cache = array();

    /**
     * Set object configuration if given
     *
     * @param  array|Zend_Config|null $config
     * @return null
     */
    public function  __construct($config=null)
    {
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
     * Try to find file for processing
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
        $conditional = $this->_getItemConditional($item);
        if (!empty($conditional) && is_string($conditional)) {
            return false;
        }

        // Don't cache items with specail comment in source
        if (!empty($item->source) && false === strpos($item->source, self::NON_CACHE_COMMENT)) {
            return true;
        }

        if (!empty ($item->media) && 'screen' !== $item->media
                || (!empty($item->rel) && 'stylesheet' !== $item->rel)) {
            return false;
        }

        // Cache file by file, found by SRC attribute
        $path = $this->_getItemPath($item);
        return (isset($path) && $this->searchFile($path));
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
            $path = $this->searchFile($this->_getItemPath($item));
            $this->_cache[] = array(
                'filepath' => $path,
                'mtime'    => filemtime($path)
            );
        }
    }

    /**
     * Save gzipped content in .gz file
     *
     * @param  string $path
     * @param  string $content
     * @return null
     */
    public function gzip($path, $content)
    {
        $compress = $this->getOption('gzcompress');
        if ($compress > 0) {
            file_put_contents($path . '.gz', self::GZIP_COMPRESS_HEADER . gzcompress($content, $compress));
        }
    }

    /**
     * Build full filename by ending it with given extension and IS_COMPRESSED suffix
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
        return '/' . ltrim(str_replace($this->getServerPath(''), '', $path), '/');
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
     * Return path to file described in item
     *
     * @param  stdClass $item
     * @return string|null
     */
    protected function _getItemPath($item)
    {
        return empty($item->attributes['src']) ? null : $item->attributes['src'];
    }

    /**
     * Return conditional attributes for item
     *
     * @param  stdClass $item
     * @return string|null
     */
    protected function _getItemConditional($item)
    {
        return empty($item->attributes['conditional']) ? null : $item->attributes['conditional'];
    }
}