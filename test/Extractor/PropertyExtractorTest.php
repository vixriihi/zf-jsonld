<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2015 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZFTest\JsonLD\Extractor;

use PHPUnit_Framework_TestCase as TestCase;
use Zend\Http\Request;
use Zend\Mvc\Router\Http\TreeRouteStack;
use Zend\Mvc\Router\RouteMatch;
use Zend\View\Helper\Url as UrlHelper;
use ZF\JsonLD\Extractor\PropertyExtractor;
use ZF\JsonLD\Property\Property;

class PropertyExtractorTest extends TestCase
{
    public function testExtractGivenIncompletePropertyShouldThrowException()
    {
        $serverUrlHelper = $this->getMock('Zend\View\Helper\ServerUrl');
        $urlHelper       = $this->getMock('Zend\View\Helper\Url');

        $propertyExtractor = new PropertyExtractor($serverUrlHelper, $urlHelper);

        $property = $this->getMockBuilder('ZF\JsonLD\Property\Property')
            ->disableOriginalConstructor()
            ->getMock();

        $property
            ->expects($this->once())
            ->method('isComplete')
            ->will($this->returnValue(false));

        $this->setExpectedException('ZF\ApiProblem\Exception\DomainException');
        $propertyExtractor->extract($property);
    }

    public function testExtractGivenPropertyWithUrlShouldReturnThisOne()
    {
        $serverUrlHelper = $this->getMock('Zend\View\Helper\ServerUrl');
        $urlHelper       = $this->getMock('Zend\View\Helper\Url');

        $propertyExtractor = new PropertyExtractor($serverUrlHelper, $urlHelper);

        $params = [
            'key' => 'resource',
            'url' => 'http://api.example.com',
        ];
        $property = Property::factory($params);

        $result = $propertyExtractor->extract($property);

        $this->assertEquals($params['url'], $result);
    }

    /**
     * @group 95
     */
    public function testPassingFalseReuseParamsOptionShouldOmitMatchedParametersInGeneratedProperty()
    {
        $serverUrlHelper = $this->getMock('Zend\View\Helper\ServerUrl');
        $urlHelper       = new UrlHelper;

        $propertyExtractor = new PropertyExtractor($serverUrlHelper, $urlHelper);

        $match = $this->matchUrl('/resource/foo', $urlHelper);
        $this->assertEquals('foo', $match->getParam('id', false));

        $property = Property::factory([
            'key' => 'resource',
            'route' => [
                'name' => 'hostname/resource',
                'options' => [
                    'reuse_matched_params' => false,
                ],
            ],
        ]);

        $result = $propertyExtractor->extract($property);

        $this->assertInternalType('string', $result);
        $this->assertEquals('http://localhost.localdomain/resource', $result);
    }

    private function matchUrl($url, $urlHelper)
    {
        $url     = 'http://localhost.localdomain' . $url;
        $request = new Request();
        $request->setUri($url);

        $router = new TreeRouteStack();

        $router->addRoute('hostname', [
            'type' => 'hostname',
            'options' => [
                'route' => 'localhost.localdomain',
            ],
            'child_routes' => [
                'resource' => [
                    'type' => 'segment',
                    'options' => [
                        'route' => '/resource[/:id]'
                    ],
                ],
            ]
        ]);

        $match = $router->match($request);
        if ($match instanceof RouteMatch) {
            $urlHelper->setRouter($router);
            $urlHelper->setRouteMatch($match);
        }

        return $match;
    }
}
