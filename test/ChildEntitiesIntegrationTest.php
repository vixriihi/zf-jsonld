<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZFTest\JsonLD;

use PHPUnit_Framework_TestCase as TestCase;
use Zend\Http\Request;
use Zend\Mvc\Controller\PluginManager as ControllerPluginManager;
use Zend\Mvc\Router\Http\TreeRouteStack;
use Zend\View\HelperPluginManager;
use Zend\View\Helper\ServerUrl as ServerUrlHelper;
use Zend\View\Helper\Url as UrlHelper;
use ZF\ApiProblem\View\ApiProblemRenderer;
use ZF\JsonLD\Collection;
use ZF\JsonLD\Entity;
use ZF\JsonLD\Extractor\PropertyCollectionExtractor;
use ZF\JsonLD\Extractor\PropertyExtractor;
use ZF\JsonLD\Property\Property;
use ZF\JsonLD\Plugin\JsonLD as JsonLDHelper;
use ZF\JsonLD\View\JsonLDModel;
use ZF\JsonLD\View\JsonLDRenderer;

/**
 * @subpackage UnitTest
 */
class ChildEntitiesIntegrationTest extends TestCase
{
    public function setUp()
    {
        $this->setupRouter();
        $this->setupHelpers();
        $this->setupRenderer();
    }

    public function setupHelpers()
    {
        if (!$this->router) {
            $this->setupRouter();
        }

        $urlHelper = new UrlHelper();
        $urlHelper->setRouter($this->router);

        $serverUrlHelper = new ServerUrlHelper();
        $serverUrlHelper->setScheme('http');
        $serverUrlHelper->setHost('localhost.localdomain');

        $propertiesHelper = new JsonLDHelper();
        $propertiesHelper->setUrlHelper($urlHelper);
        $propertiesHelper->setServerUrlHelper($serverUrlHelper);

        $propertyExtractor = new PropertyExtractor($serverUrlHelper, $urlHelper);
        $propertyCollectionExtractor = new PropertyCollectionExtractor($propertyExtractor);
        $propertiesHelper->setPropertyCollectionExtractor($propertyCollectionExtractor);

        $this->helpers = $helpers = new HelperPluginManager();
        $helpers->setService('url', $urlHelper);
        $helpers->setService('serverUrl', $serverUrlHelper);
        $helpers->setService('JsonLD', $propertiesHelper);

        $this->plugins = $plugins = new ControllerPluginManager();
        $plugins->setService('JsonLD', $propertiesHelper);
    }

    public function setupRenderer()
    {
        if (!$this->helpers) {
            $this->setupHelpers();
        }
        $this->renderer = $renderer = new JsonLDRenderer(new ApiProblemRenderer());
        $renderer->setHelperPluginManager($this->helpers);
    }

    public function setupRouter()
    {
        $routes = [
            'parent' => [
                'type' => 'Segment',
                'options' => [
                    'route' => '/api/parent[/:parent]',
                    'defaults' => [
                        'controller' => 'Api\ParentController',
                    ],
                ],
                'may_terminate' => true,
                'child_routes' => [
                    'child' => [
                        'type' => 'Segment',
                        'options' => [
                            'route' => '/child[/:child]',
                            'defaults' => [
                                'controller' => 'Api\ChildController',
                            ],
                        ],
                    ],
                ],
            ],
        ];
        $this->router = $router = new TreeRouteStack();
        $router->addRoutes($routes);
    }

    public function setUpParentEntity()
    {
        $this->parent = (object) [
            '@id'  => 'anakin',
            'name' => 'Anakin Skywalker',
        ];
        $entity = new Entity($this->parent, 'anakin');

        $property = new Property('some');
        $property->setRoute('parent');
        $property->setRouteParams(['parent'=> 'anakin']);
        $entity->getProperties()->add($property);

        return $entity;
    }

    public function setUpChildEntity($id, $name)
    {
        $this->child = (object) [
            '@id'   => $id,
            'name' => $name,
        ];
        $entity = new Entity($this->child, $id);

        $property = new Property('some');
        $property->setRoute('parent/child');
        $property->setRouteParams(['child'=> $id]);
        $entity->getProperties()->add($property);

        return $entity;
    }

    public function setUpChildCollection()
    {
        $children = [
            ['luke', 'Luke Skywalker'],
            ['leia', 'Leia Organa'],
        ];
        $collection = [];
        foreach ($children as $info) {
            $collection[] = call_user_func_array([$this, 'setUpChildEntity'], $info);
        }
        $collection = new Collection($collection);
        $collection->setCollectionRoute('parent/child');
        $collection->setEntityRoute('parent/child');
        $collection->setPage(1);
        $collection->setPageSize(10);
        $collection->setCollectionName('child');

        $property = new Property('@id');
        $property->setRoute('parent/child');
        $collection->getProperties()->add($property);

        return $collection;
    }

    public function testParentEntityRendersAsExpected()
    {
        $uri = 'http://localhost.localdomain/api/parent/anakin';
        $request = new Request();
        $request->setUri($uri);
        $matches = $this->router->match($request);
        $this->assertInstanceOf('Zend\Mvc\Router\RouteMatch', $matches);
        $this->assertEquals('anakin', $matches->getParam('parent'));
        $this->assertEquals('parent', $matches->getMatchedRouteName());

        // Emulate url helper factory and inject route matches
        $this->helpers->get('url')->setRouteMatch($matches);

        $parent = $this->setUpParentEntity();
        $model  = new JsonLDModel();
        $model->setPayload($parent);

        $json = $this->renderer->render($model);
        $test = json_decode($json);
        $this->assertObjectHasAttribute('@id', $test);
        $this->assertObjectHasAttribute('some', $test);
        $this->assertEquals('http://localhost.localdomain/api/parent/anakin', $test->some);
    }

    public function testChildEntityRendersAsExpected()
    {
        $uri = 'http://localhost.localdomain/api/parent/anakin/child/luke';
        $request = new Request();
        $request->setUri($uri);
        $matches = $this->router->match($request);
        $this->assertInstanceOf('Zend\Mvc\Router\RouteMatch', $matches);
        $this->assertEquals('anakin', $matches->getParam('parent'));
        $this->assertEquals('luke', $matches->getParam('child'));
        $this->assertEquals('parent/child', $matches->getMatchedRouteName());

        // Emulate url helper factory and inject route matches
        $this->helpers->get('url')->setRouteMatch($matches);

        $child = $this->setUpChildEntity('luke', 'Luke Skywalker');
        $model = new JsonLDModel();
        $model->setPayload($child);

        $json = $this->renderer->render($model);
        $test = json_decode($json);
        $this->assertObjectHasAttribute('some', $test);
        $this->assertEquals('http://localhost.localdomain/api/parent/anakin/child/luke', $test->some);
    }

    public function testChildCollectionRendersAsExpected()
    {
        $uri = 'http://localhost.localdomain/api/parent/anakin/child';
        $request = new Request();
        $request->setUri($uri);
        $matches = $this->router->match($request);
        $this->assertInstanceOf('Zend\Mvc\Router\RouteMatch', $matches);
        $this->assertEquals('anakin', $matches->getParam('parent'));
        $this->assertNull($matches->getParam('child'));
        $this->assertEquals('parent/child', $matches->getMatchedRouteName());

        // Emulate url helper factory and inject route matches
        $this->helpers->get('url')->setRouteMatch($matches);

        $collection = $this->setUpChildCollection();
        $model = new JsonLDModel();
        $model->setPayload($collection);

        $json = $this->renderer->render($model);
        $test = json_decode($json);
        $this->assertObjectHasAttribute('@id', $test);
        $this->assertEquals('http://localhost.localdomain/api/parent/anakin/child', $test->{'@id'});

        $this->assertObjectHasAttribute('child', $test);
        $this->assertInternalType('array', $test->child);

        foreach ($test->child as $child) {
            $this->assertObjectHasAttribute('@id', $child);
            $this->assertObjectHasAttribute('some', $child);
            $this->assertRegexp(
                '#^http://localhost.localdomain/api/parent/anakin/child/[^/]+$#',
                $child->some
            );
        }
    }

    public function setUpAlternateRouter()
    {
        $routes = [
            'parent' => [
                'type' => 'Segment',
                'options' => [
                    'route' => '/api/parent[/:id]',
                    'defaults' => [
                        'controller' => 'Api\ParentController',
                    ],
                ],
                'may_terminate' => true,
                'child_routes' => [
                    'child' => [
                        'type' => 'Segment',
                        'options' => [
                            'route' => '/child[/:child]',
                            'defaults' => [
                                'controller' => 'Api\ChildController',
                            ],
                        ],
                    ],
                ],
            ],
        ];
        $this->router = $router = new TreeRouteStack();
        $router->addRoutes($routes);
        $this->helpers->get('url')->setRouter($router);
    }

    public function testChildEntityObjectIdentifierMapping()
    {
        $this->setUpAlternateRouter();

        $uri = 'http://localhost.localdomain/api/parent/anakin/child/luke';
        $request = new Request();
        $request->setUri($uri);
        $matches = $this->router->match($request);
        $this->assertInstanceOf('Zend\Mvc\Router\RouteMatch', $matches);
        $this->assertEquals('anakin', $matches->getParam('id'));
        $this->assertEquals('luke', $matches->getParam('child'));
        $this->assertEquals('parent/child', $matches->getMatchedRouteName());

        // Emulate url helper factory and inject route matches
        $this->helpers->get('url')->setRouteMatch($matches);

        $child = $this->setUpChildEntity('luke', 'Luke Skywalker');
        $model = new JsonLDModel();
        $model->setPayload($child);

        $json = $this->renderer->render($model);
        $test = json_decode($json);
        $this->assertObjectHasAttribute('@id', $test);
        $this->assertObjectHasAttribute('some', $test);
        $this->assertEquals('http://localhost.localdomain/api/parent/anakin/child/luke', $test->some);
    }

    public function testChildEntityIdentifierMappingInsideCollection()
    {
        $this->setUpAlternateRouter();

        $uri = 'http://localhost.localdomain/api/parent/anakin/child';
        $request = new Request();
        $request->setUri($uri);
        $matches = $this->router->match($request);
        $this->assertInstanceOf('Zend\Mvc\Router\RouteMatch', $matches);
        $this->assertEquals('anakin', $matches->getParam('id'));
        $this->assertNull($matches->getParam('child'));
        $this->assertEquals('parent/child', $matches->getMatchedRouteName());

        // Emulate url helper factory and inject route matches
        $this->helpers->get('url')->setRouteMatch($matches);

        $collection = $this->setUpChildCollection();
        $model = new JsonLDModel();
        $model->setPayload($collection);

        $json = $this->renderer->render($model);
        $test = json_decode($json);
        $this->assertObjectHasAttribute('@id', $test);
        $this->assertEquals('http://localhost.localdomain/api/parent/anakin/child', $test->{'@id'});

        $this->assertObjectHasAttribute('child', $test);
        $this->assertInternalType('array', $test->child);

        foreach ($test->child as $child) {
            $this->assertObjectHasAttribute('@id', $child);
            $this->assertObjectHasAttribute('some', $child);
            $this->assertRegexp(
                '#^http://localhost.localdomain/api/parent/anakin/child/[^/]+$#',
                $child->some
            );
        }
    }
}
