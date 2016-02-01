<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZFTest\JsonLD\Factory;

use PHPUnit_Framework_TestCase as TestCase;
use Zend\ServiceManager\ServiceManager;
use ZF\JsonLD\Factory\JsonLDStrategyFactory;

class JsonLDStrategyFactoryTest extends TestCase
{
    public function testInstantiatesHalJsonStrategy()
    {
        $services = new ServiceManager();

        $jsonLDJsonRenderer = $this->getMockBuilder('ZF\JsonLD\View\JsonLDRenderer')
            ->disableOriginalConstructor()
            ->getMock();

        $services->setService('ZF\JsonLD\JsonRenderer', $jsonLDJsonRenderer);

        $factory = new JsonLDStrategyFactory();
        $strategy = $factory->createService($services);

        $this->assertInstanceOf('ZF\JsonLD\View\JsonLDStrategy', $strategy);
    }
}
