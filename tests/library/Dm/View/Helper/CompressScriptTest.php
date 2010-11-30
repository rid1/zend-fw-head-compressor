<?php

/**
 * Test file for compress script helper
 *
 * @package    View
 * @subpackage Helper
 * @version    0.3.1
 * @author     Alexey S. Kachayev <kachayev@gmail.com>
 * @link       https://github.com/kachayev/zend-fw-head-compressor
 */
class Dm_View_Helper_CompressScriptTest
    extends DmTestCase
{
    /**
     * @var Dm_View_Helper_CompressScript
     */
    protected $_helper = null;

    /**
     * @var Zend_View
     */
    protected $_view = null;

    /**
     * New helper object for each test case
     */
    public function setUp()
    {
        parent::setUp();

        $this->_view   = new Zend_View();
        $this->_helper = new Dm_View_Helper_CompressScript();
        $this->_helper->setView($this->_view);
    }

    public function testGetOptionShouldReturnActualValue()
    {
        $this->assertTrue($this->_helper->getOption('combine'));
    }
    
    public function testGetOptionWithNonExistenKeyShouldReturnDefaultValue()
    {
        $this->assertEquals(50, $this->_helper->getOption('key-which_don`t-exists', 50));
    }

    public function testSetConfigShouldOverwriteDefaultConfiguration()
    {
        $this->_helper->setConfig(array('compress'=>false));
        $this->_assertOverwritedCompressOption();
    }
    
    public function testSetConfigShouldUnderstandConfigObject()
    {
        $this->_helper->setConfig(new Zend_Config(array('compress'=>false)));
        $this->_assertOverwritedCompressOption();
    }

    protected function _assertOverwritedCompressOption()
    {
        $this->assertFalse($this->_helper->getOption('compress'), 'Overwrited value');
        $this->assertTrue($this->_helper->getOption('combine'), 'Default value'); 
    }

    public function testCompressScriptWithConfigParamShouldOverwriteConfig()
    {
        $this->_helper->compressScript(new Zend_Config(array('compress'=>false, 'combine'=>false)));
        $this->assertFalse($this->_helper->getOption('compress'));
    }

    public function testCompressScriptWithoutCombineShouldReturnHeadScript()
    {
        $this->_helper->setConfig(new Zend_Config(array('compress'=>false, 'combine'=>false)));
        $result = $this->_helper->compressScript();

        $this->assertTrue($result instanceof Zend_View_Helper_HeadScript, 'Is head script object');
        $this->assertFalse($result instanceof Dm_View_Helper_CompressScript, 'Non self');
    }
    
    public function testCompressScriptWithCombineShouldReturnString()
    {
        $this->assertTrue(is_string($this->_helper->compressScript()));
    }

    public function testGetProcessorShouldReturnObjectOfDefaultClass()
    {
        $this->assertType('Dm_View_Helper_Head_File', $this->_helper->getProcessor());
    }
}