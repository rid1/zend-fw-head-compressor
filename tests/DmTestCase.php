<?php

require_once 'Zend/Application.php';
require_once 'Zend/Test/PHPUnit/ControllerTestCase.php';

abstract class DmTestCase extends Zend_Test_PHPUnit_ControllerTestCase
{
    /**
     * Application hendler
     *
     * @var Zend_Application
     */
    protected $_application;

    public function setUp()
    {
        $this->bootstrap = array($this, 'appBootstrap');
        parent::setUp();
    }

    public function appBootstrap()
    {
        $this->_application = new Zend_Application(
            APPLICATION_ENV,
            APPLICATION_PATH . '/configs/application.ini'
        );

        $this->_application->bootstrap();
    }

    public function tearDown()
    {
        Zend_Controller_Front::getInstance()->resetInstance();
        $this->resetRequest();
        $this->resetResponse();

        $this->request->setPost(array());
        $this->request->setQuery(array());
    }

    public function dispatch($url = null)
    {
        // redirector should not exit
        $redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');
        $redirector->setExit(false);

        // json helper should not exit
        $json = Zend_Controller_Action_HelperBroker::getStaticHelper('json');
        $json->suppressExit = true;

        $request = $this->getRequest();

        if (null !== $url) {
            $request->setRequestUri($url);
        }

        $request->setPathInfo(null);

        $frontController = $this->getFrontController();
        $frontController
            ->setRequest($request)
            ->setResponse($this->getResponse())
            ->throwExceptions(true)
            ->returnResponse(true);

        $frontController->dispatch();
    }
}