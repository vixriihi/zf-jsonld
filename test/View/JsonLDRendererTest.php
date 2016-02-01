<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZFTest\JsonLD\View;

use PHPUnit_Framework_TestCase as TestCase;
use Zend\View\Model\JsonModel;
use Zend\View\Model\ViewModel;
use ZF\ApiProblem\ApiProblem;
use ZF\ApiProblem\View\ApiProblemRenderer;
use ZF\JsonLD\Collection;
use ZF\JsonLD\Entity;
use ZF\JsonLD\Extractor\PropertyCollectionExtractor;
use ZF\JsonLD\Extractor\PropertyExtractor;
use ZF\JsonLD\Property\Property;
use ZF\JsonLD\Plugin\JsonLD as HalHelper;
use ZF\JsonLD\View\JsonLDModel;
use ZF\JsonLD\View\JsonLDRenderer;

/**
 * @subpackage UnitTest
 */
class JsonLDRendererTest extends TestCase
{
    /**
     * @var JsonLDRenderer
     */
    protected $renderer;

    public function setUp()
    {
        $this->renderer = new JsonLDRenderer(new ApiProblemRenderer());
    }

    public function nonHalJsonModels()
    {
        return [
            'view-model'      => [new ViewModel(['name' => 'foo'])],
            'json-view-model' => [new JsonModel(['name' => 'foo'])],
        ];
    }

    /**
     * @dataProvider nonHalJsonModels
     */
    public function testRenderGivenNonHalJsonModelShouldReturnDataInJsonFormat($model)
    {
        $payload = $this->renderer->render($model);
        $expected = json_encode(['foo' => 'bar']);

        $this->assertEquals(
            $model->getVariables(),
            json_decode($payload, true)
        );
    }

    public function testRenderGivenHalJsonModelThatContainsHalEntityShouldReturnDataInJsonFormat()
    {
        $entity = [
            'id'   => 123,
            'name' => 'foo',
        ];
        $jsonLDEntity = new Entity($entity, 123);
        $model = new JsonLDModel(['payload' => $jsonLDEntity]);

        $helperPluginManager = $this->getHelperPluginManager();

        $jsonLDPlugin = $helperPluginManager->get('JsonLD');
        $jsonLDPlugin
            ->expects($this->once())
            ->method('renderEntity')
            ->with($jsonLDEntity)
            ->will($this->returnValue($entity));

        $this->renderer->setHelperPluginManager($helperPluginManager);

        $rendered = $this->renderer->render($model);

        $this->assertEquals($entity, json_decode($rendered, true));
    }

    public function testRenderGivenHalJsonModelThatContainsHalCollectionShouldReturnDataInJsonFormat()
    {
        $collection = [
            ['id' => 'foo', 'name' => 'foo'],
            ['id' => 'bar', 'name' => 'bar'],
            ['id' => 'baz', 'name' => 'baz'],
        ];
        $jsonLDCollection = new Collection($collection);
        $model = new JsonLDModel(['payload' => $jsonLDCollection]);

        $helperPluginManager = $this->getHelperPluginManager();

        $jsonLDPlugin = $helperPluginManager->get('JsonLD');
        $jsonLDPlugin
            ->expects($this->once())
            ->method('renderCollection')
            ->with($jsonLDCollection)
            ->will($this->returnValue($collection));

        $this->renderer->setHelperPluginManager($helperPluginManager);

        $rendered = $this->renderer->render($model);

        $this->assertEquals($collection, json_decode($rendered, true));
    }

    public function testRenderGivenHalJsonModelReturningApiProblemShouldReturnApiProblemInJsonFormat()
    {
        $jsonLDCollection = new Collection([]);
        $model = new JsonLDModel(['payload' => $jsonLDCollection]);

        $apiProblem = new ApiProblem(500, 'error');

        $helperPluginManager = $this->getHelperPluginManager();

        $jsonLDPlugin = $helperPluginManager->get('JsonLD');
        $jsonLDPlugin
            ->expects($this->once())
            ->method('renderCollection')
            ->with($jsonLDCollection)
            ->will($this->returnValue($apiProblem));

        $this->renderer->setHelperPluginManager($helperPluginManager);

        $rendered = $this->renderer->render($model);

        $apiProblemData = [
            'type'   => 'http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html',
            'title'  => 'Internal Server Error',
            'status' => 500,
            'detail' => 'error',
        ];
        $this->assertEquals($apiProblemData, json_decode($rendered, true));
    }

    private function getHelperPluginManager()
    {
        $helperPluginManager = $this->getMockBuilder('Zend\View\HelperPluginManager')
            ->disableOriginalConstructor()
            ->getMock();

        $jsonLDPlugin = $this->getMock('ZF\JsonLD\Plugin\JsonLD');

        $helperPluginManager
            ->method('get')
            ->with('JsonLD')
            ->will($this->returnValue($jsonLDPlugin));

        return $helperPluginManager;
    }
}
