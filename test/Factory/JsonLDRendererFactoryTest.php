<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZFTest\JsonLD\Factory;

use PHPUnit_Framework_TestCase as TestCase;
use Zend\ServiceManager\ServiceManager;
use ZF\ApiProblem\View\ApiProblemRenderer;
use ZF\JsonLD\Factory\JsonLDRendererFactory;

class JsonLDRendererFactoryTest extends TestCase
{
    public function testInstantiatesHalJsonRenderer()
    {
        $services = new ServiceManager();

        $viewHelperManager = $this->getMockBuilder('Zend\View\HelperPluginManager')
            ->disableOriginalConstructor()
            ->getMock();

        $services->setService('ViewHelperManager', $viewHelperManager);

        $services->setService('ZF\ApiProblem\ApiProblemRenderer', new ApiProblemRenderer());

        $factory = new JsonLDRendererFactory();
        $renderer = $factory->createService($services);

        $this->assertInstanceOf('ZF\JsonLD\View\JsonLDRenderer', $renderer);
    }
}
