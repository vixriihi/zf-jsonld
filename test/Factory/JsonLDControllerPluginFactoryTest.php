<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZFTest\JsonLD\Factory;

use PHPUnit_Framework_TestCase as TestCase;
use Zend\ServiceManager\ServiceManager;
use ZF\JsonLD\Factory\JsonLDControllerPluginFactory;
use ZF\JsonLD\Plugin\JsonLD as HalPlugin;

class JsonLDControllerPluginFactoryTest extends TestCase
{
    public function testInstantiatesHalJsonRenderer()
    {
        $viewHelperManager = $this->getMockBuilder('Zend\View\HelperPluginManager')
            ->disableOriginalConstructor()
            ->getMock();
        $viewHelperManager
            ->expects($this->once())
            ->method('get')
            ->will($this->returnValue(new HalPlugin()));

        $services = new ServiceManager();
        $services->setService('ViewHelperManager', $viewHelperManager);

        $pluginManager = $this->getMock('Zend\ServiceManager\AbstractPluginManager');
        $pluginManager
            ->expects($this->once())
            ->method('getServiceLocator')
            ->will($this->returnValue($services));

        $factory = new JsonLDControllerPluginFactory();
        $plugin = $factory->createService($pluginManager);

        $this->assertInstanceOf('ZF\JsonLD\Plugin\JsonLD', $plugin);
    }
}
