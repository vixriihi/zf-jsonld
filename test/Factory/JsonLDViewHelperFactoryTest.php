<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZFTest\JsonLD\Factory;

use PHPUnit_Framework_TestCase as TestCase;
use ReflectionObject;
use Zend\ServiceManager\ServiceManager;
use Zend\Hydrator\HydratorPluginManager;
use Zend\View\Helper\ServerUrl;
use Zend\View\Helper\Url;
use ZF\JsonLD\Factory\JsonLDViewHelperFactory;
use ZF\JsonLD\RendererOptions;

class JsonLDViewHelperFactoryTest extends TestCase
{
    public function setupPluginManager($config = [])
    {
        $services = new ServiceManager();

        $services->setService('ZF\JsonLD\JsonLDConfig', $config);

        if (isset($config['renderer']) && is_array($config['renderer'])) {
            $rendererOptions = new RendererOptions($config['renderer']);
        } else {
            $rendererOptions = new RendererOptions();
        }
        $services->setService('ZF\JsonLD\RendererOptions', $rendererOptions);

        $metadataMap = $this->getMock('ZF\JsonLD\Metadata\MetadataMap');
        $metadataMap
            ->expects($this->once())
            ->method('getHydratorManager')
            ->will($this->returnValue(new HydratorPluginManager()));

        $services->setService('ZF\JsonLD\MetadataMap', $metadataMap);

        $this->pluginManager = $this->getMock('Zend\ServiceManager\AbstractPluginManager');

        $this->pluginManager
            ->expects($this->at(1))
            ->method('get')
            ->with('ServerUrl')
            ->will($this->returnValue(new ServerUrl()));

        $this->pluginManager
            ->expects($this->at(2))
            ->method('get')
            ->with('Url')
            ->will($this->returnValue(new Url()));

        $this->pluginManager
            ->method('getServiceLocator')
            ->will($this->returnValue($services));
    }

    public function testInstantiatesHalViewHelper()
    {
        $this->setupPluginManager();

        $factory = new JsonLDViewHelperFactory();
        $plugin = $factory->createService($this->pluginManager);

        $this->assertInstanceOf('ZF\JsonLD\Plugin\JsonLD', $plugin);
    }

    /**
     * @group fail
     */
    public function testOptionUseProxyIfPresentInConfig()
    {
        $options = [
            'options' => [
                'use_proxy' => true,
            ],
        ];

        $this->setupPluginManager($options);

        $factory = new JsonLDViewHelperFactory();
        $jsonLDPlugin = $factory->createService($this->pluginManager);

        $r = new ReflectionObject($jsonLDPlugin);
        $p = $r->getProperty('serverUrlHelper');
        $p->setAccessible(true);
        $serverUrlPlugin = $p->getValue($jsonLDPlugin);
        $this->assertInstanceOf('Zend\View\Helper\ServerUrl', $serverUrlPlugin);

        $r = new ReflectionObject($serverUrlPlugin);
        $p = $r->getProperty('useProxy');
        $p->setAccessible(true);
        $useProxy = $p->getValue($serverUrlPlugin);
        $this->assertInternalType('boolean', $useProxy);
        $this->assertTrue($useProxy);
    }
}
