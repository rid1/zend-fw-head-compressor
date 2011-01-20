<?php

/**
 * Processor for handling operation with css files
 *
 * @package    View
 * @subpackage Helper
 * @version    0.0.1
 * @author     Alexey S. Kachayev <kachayev@gmail.com>
 * @link       https://github.com/kachayev/zend-fw-head-compressor
 */
class Dm_View_Helper_Head_FileStylesheet
    extends Dm_View_Helper_Head_File
{
    /**
     * Return path to file described in item
     *
     * @param  stdClass $item
     * @return string|null
     */
    protected function _getItemPath($item)
    {
        return empty($item->href) ? null : $item->href;
    }

    /**
     * Return conditional attributes for item
     *
     * @param  stdClass $item
     * @return string|null
     */
    protected function _getItemConditional($item)
    {
        return isset($item->conditionalStylesheet) ? $item->conditionalStylesheet : false;
    }
}