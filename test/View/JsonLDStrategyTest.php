<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZFTest\JsonLD\View;

use PHPUnit_Framework_TestCase as TestCase;
use Zend\Http\Response;
use Zend\View\ViewEvent;
use ZF\ApiProblem\View\ApiProblemRenderer;
use ZF\JsonLD\Collection;
use ZF\JsonLD\Entity;
use ZF\JsonLD\Property\Property;
use ZF\JsonLD\View\JsonLDModel;
use ZF\JsonLD\View\JsonLDRenderer;
use ZF\JsonLD\View\JsonLDStrategy;

/**
 * @subpackage UnitTest
 */
class JsonLDStrategyTest extends TestCase
{
    public function setUp()
    {
        $this->response = new Response;
        $this->event    = new ViewEvent;
        $this->event->setResponse($this->response);

        $this->renderer = new JsonLDRenderer(new ApiProblemRenderer());
        $this->strategy = new JsonLDStrategy($this->renderer);
    }

    public function testSelectRendererReturnsNullIfModelIsNotAHalJsonModel()
    {
        $this->assertNull($this->strategy->selectRenderer($this->event));
    }

    public function testSelectRendererReturnsRendererIfModelIsAHalJsonModel()
    {
        $model = new JsonLDModel();
        $this->event->setModel($model);
        $this->assertSame($this->renderer, $this->strategy->selectRenderer($this->event));
    }

    public function testInjectResponseDoesNotSetContentTypeHeaderIfRendererDoesNotMatch()
    {
        $this->strategy->injectResponse($this->event);
        $headers = $this->response->getHeaders();
        $this->assertFalse($headers->has('Content-Type'));
    }

    public function testInjectResponseDoesNotSetContentTypeHeaderIfResultIsNotString()
    {
        $this->event->setRenderer($this->renderer);
        $this->event->setResult(['foo']);
        $this->strategy->injectResponse($this->event);
        $headers = $this->response->getHeaders();
        $this->assertFalse($headers->has('Content-Type'));
    }

    public function testInjectResponseSetsContentTypeHeaderToDefaultIfNotHalModel()
    {
        $this->event->setRenderer($this->renderer);
        $this->event->setResult('{"foo":"bar"}');
        $this->strategy->injectResponse($this->event);
        $headers = $this->response->getHeaders();
        $this->assertTrue($headers->has('Content-Type'));
        $header = $headers->get('Content-Type');
        $this->assertEquals('application/json', $header->getFieldValue());
    }

    public function jsonLDObjects()
    {
        $entity = new Entity([
            'foo' => 'bar',
        ], 'identifier', 'route');
        $property = new Property('self');
        $property->setRoute('resource/route')->setRouteParams(['id' => 'identifier']);
        $entity->getProperties()->add($property);

        $collection = new Collection([$entity]);
        $collection->setCollectionRoute('collection/route');
        $collection->setEntityRoute('resource/route');

        return [
            'entity'     => [$entity],
            'collection' => [$collection],
        ];
    }

    /**
     * @dataProvider jsonLDObjects
     */
    public function testInjectResponseSetsContentTypeHeaderToHalForHalModel($jsonLD)
    {
        $model = new JsonLDModel(['payload' => $jsonLD]);

        $this->event->setModel($model);
        $this->event->setRenderer($this->renderer);
        $this->event->setResult('{"foo":"bar"}');
        $this->strategy->injectResponse($this->event);
        $headers = $this->response->getHeaders();
        $this->assertTrue($headers->has('Content-Type'));
        $header = $headers->get('Content-Type');
        $this->assertEquals('application/ld+json', $header->getFieldValue());
    }
}
