<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZFTest\JsonLD\Property;

use ZF\JsonLD\Property\Property;
use PHPUnit_Framework_TestCase as TestCase;

class PropertyTest extends TestCase
{
    public function testConstructorTakesPropertyRelationName()
    {
        $property = new Property('describedby');
        $this->assertEquals('describedby', $property->getKeyword());
    }

    public function testCanSetPropertyUrl()
    {
        $url  = 'http://example.com/docs.html';
        $property = new Property('describedby');
        $property->setUrl($url);
        $this->assertEquals($url, $property->getUrl());
    }

    public function testCanSetPropertyRoute()
    {
        $route = 'api/docs';
        $property = new Property('describedby');
        $property->setRoute($route);
        $this->assertEquals($route, $property->getRoute());
    }

    public function testCanSetRouteParamsWhenSpecifyingRoute()
    {
        $route  = 'api/docs';
        $params = ['version' => '1.1'];
        $property = new Property('describedby');
        $property->setRoute($route, $params);
        $this->assertEquals($route, $property->getRoute());
        $this->assertEquals($params, $property->getRouteParams());
    }

    public function testCanSetRouteOptionsWhenSpecifyingRoute()
    {
        $route   = 'api/docs';
        $options = ['query' => 'version=1.1'];
        $property = new Property('describedby');
        $property->setRoute($route, null, $options);
        $this->assertEquals($route, $property->getRoute());
        $this->assertEquals($options, $property->getRouteOptions());
    }

    public function testCanSetRouteParamsSeparately()
    {
        $route  = 'api/docs';
        $params = ['version' => '1.1'];
        $property = new Property('describedby');
        $property->setRoute($route);
        $property->setRouteParams($params);
        $this->assertEquals($route, $property->getRoute());
        $this->assertEquals($params, $property->getRouteParams());
    }

    public function testCanSetRouteOptionsSeparately()
    {
        $route   = 'api/docs';
        $options = ['query' => 'version=1.1'];
        $property = new Property('describedby');
        $property->setRoute($route);
        $property->setRouteOptions($options);
        $this->assertEquals($route, $property->getRoute());
        $this->assertEquals($options, $property->getRouteOptions());
    }

    public function testSettingUrlAfterSettingRouteRaisesException()
    {
        $property = new Property('describedby');
        $property->setRoute('api/docs');

        $this->setExpectedException('ZF\ApiProblem\Exception\DomainException');
        $property->setUrl('http://example.com/api/docs.html');
    }

    public function testSettingRouteAfterSettingUrlRaisesException()
    {
        $property = new Property('describedby');
        $property->setUrl('http://example.com/api/docs.html');

        $this->setExpectedException('ZF\ApiProblem\Exception\DomainException');
        $property->setRoute('api/docs');
    }

    public function testIsCompleteReturnsFalseIfNeitherUrlNorRouteIsSet()
    {
        $property = new Property('describedby');
        $this->assertFalse($property->isComplete());
    }

    public function testHasUrlReturnsFalseWhenUrlIsNotSet()
    {
        $property = new Property('describedby');
        $this->assertFalse($property->hasUrl());
    }

    public function testHasUrlReturnsTrueWhenUrlIsSet()
    {
        $property = new Property('describedby');
        $property->setUrl('http://example.com/api/docs.html');
        $this->assertTrue($property->hasUrl());
    }

    public function testIsCompleteReturnsTrueWhenUrlIsSet()
    {
        $property = new Property('describedby');
        $property->setUrl('http://example.com/api/docs.html');
        $this->assertTrue($property->isComplete());
    }

    public function testHasRouteReturnsFalseWhenRouteIsNotSet()
    {
        $property = new Property('describedby');
        $this->assertFalse($property->hasRoute());
    }

    public function testHasRouteReturnsTrueWhenRouteIsSet()
    {
        $property = new Property('describedby');
        $property->setRoute('api/docs');
        $this->assertTrue($property->hasRoute());
    }

    public function testIsCompleteReturnsTrueWhenRouteIsSet()
    {
        $property = new Property('describedby');
        $property->setRoute('api/docs');
        $this->assertTrue($property->isComplete());
    }

    /**
     * @group 79
     */
    public function testFactoryCanGeneratePropertyWithUrl()
    {
        $rel  = 'describedby';
        $url  = 'http://example.com/docs.html';
        $property = Property::factory([
            'key' => $rel,
            'url' => $url,
        ]);
        $this->assertInstanceOf('ZF\JsonLD\Property\Property', $property);
        $this->assertEquals($rel, $property->getKeyword());
        $this->assertEquals($url, $property->getUrl());
    }

    /**
     * @group 79
     */
    public function testFactoryCanGeneratePropertyWithRouteInformation()
    {
        $rel     = 'describedby';
        $route   = 'api/docs';
        $params  = ['version' => '1.1'];
        $options = ['query' => 'version=1.1'];
        $property = Property::factory([
            'key'   => $rel,
            'route' => [
                'name'    => $route,
                'params'  => $params,
                'options' => $options,
            ],
        ]);

        $this->assertInstanceOf('ZF\JsonLD\Property\Property', $property);
        $this->assertEquals('describedby', $property->getKeyword());
        $this->assertEquals($route, $property->getRoute());
        $this->assertEquals($params, $property->getRouteParams());
        $this->assertEquals($options, $property->getRouteOptions());
    }

}
