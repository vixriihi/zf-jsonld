<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZFTest\JsonLD;

use Zend\ServiceManager\ServiceManager;
use Zend\View\View;
use ZF\JsonLD\Module;
use ZF\JsonLD\View\JsonLDModel;
use PHPUnit_Framework_TestCase as TestCase;
use stdClass;

class ModuleTest extends TestCase
{
    public function setUp()
    {
        $this->module = new Module;
    }

    public function testOnRenderWhenMvcEventResultIsNotHalJsonModel()
    {
        $mvcEvent = $this->getMock('Zend\Mvc\MvcEvent');
        $mvcEvent
            ->expects($this->once())
            ->method('getResult')
            ->will($this->returnValue(new stdClass()));
        $mvcEvent
            ->expects($this->never())
            ->method('getTarget');

        $this->module->onRender($mvcEvent);
    }

    public function testOnRenderAttachesJsonStrategy()
    {
        $jsonLDJsonStrategy = $this->getMockBuilder('ZF\JsonLD\View\HalJsonStrategy')
            ->disableOriginalConstructor()
            ->getMock();

        $view = new View();

        $eventManager = $this->getMock('Zend\EventManager\EventManager');
        $eventManager
            ->expects($this->once())
            ->method('attach')
            ->with($jsonLDJsonStrategy, 200);

        $view->setEventManager($eventManager);

        $serviceManager = new ServiceManager();
        $serviceManager
            ->setService('ZF\JsonLD\JsonStrategy', $jsonLDJsonStrategy)
            ->setService('View', $view);

        $application = $this->getMock('Zend\Mvc\ApplicationInterface');
        $application
            ->expects($this->once())
            ->method('getServiceManager')
            ->will($this->returnValue($serviceManager));

        $mvcEvent = $this->getMock('Zend\Mvc\MvcEvent');
        $mvcEvent
            ->expects($this->at(0))
            ->method('getResult')
            ->will($this->returnValue(new JsonLDModel()));
        $mvcEvent
            ->expects($this->at(1))
            ->method('getTarget')
            ->will($this->returnValue($application));

        $this->module->onRender($mvcEvent);
    }
}
