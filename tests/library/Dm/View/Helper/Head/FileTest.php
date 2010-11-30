<?php

/**
 * Test for files processor
 *
 * @package    View
 * @subpackage Helper
 * @version    0.0.1
 * @author     Alexey S. Kachayev <kachayev@gmail.com>
 * @link       https://github.com/kachayev/zend-fw-head-compressor
 */
class Dm_View_Helper_Head_FileTest
    extends DmTestCase
{
    /**
     * @var Dm_View_Helper_Head_File
     */
    protected $_processor = null;

    /**
     * New helper object for each test case
     */
    public function setUp()
    {
        parent::setUp();

        $this->_processor = new Dm_View_Helper_Head_File();
    }

    public function testSearchFileShouldReturnNullForNonExisten()
    {
        $this->assertEquals(null, $this->_processor->searchFile('file-which-never-be'));
    }
}