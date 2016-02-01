<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZFTest\JsonLD;

use PHPUnit_Framework_TestCase as TestCase;
use Zend\Hydrator\HydratorPluginManager;
use ZF\JsonLD\EntityHydratorManager;
use ZF\JsonLD\Extractor\EntityExtractor;
use ZF\JsonLD\Metadata\MetadataMap;
use ZF\JsonLD\ResourceFactory;
use ZFTest\JsonLD\Plugin\TestAsset;

/**
 * @subpackage UnitTest
 */
class ResourceFactoryTest extends TestCase
{
    /**
     * @group 79
     */
    public function testInjectsPropertiesFromMetadataWhenCreatingEntity()
    {
        $object = new TestAsset\Entity('foo', 'Foo');

        $metadata = new MetadataMap([
            'ZFTest\JsonLD\Plugin\TestAsset\Entity' => [
                'hydrator'   => 'Zend\Hydrator\ObjectProperty',
                'route_name' => 'hostname/resource',
                'properties'      => [
                    [
                        'key' => 'describedby',
                        'url' => 'http://example.com/api/help/resource',
                    ],
                    [
                        'key' => 'children',
                        'route' => [
                            'name' => 'resource/children',
                        ],
                    ],
                ],
            ],
        ]);

        $resourceFactory = $this->getResourceFactory($metadata);

        $entity = $resourceFactory->createEntityFromMetadata(
            $object,
            $metadata->get('ZFTest\JsonLD\Plugin\TestAsset\Entity')
        );

        $this->assertInstanceof('ZF\JsonLD\Entity', $entity);
        $properties = $entity->getProperties();
        $this->assertTrue($properties->has('describedby'));
        $this->assertTrue($properties->has('children'));

        $describedby = $properties->get('describedby');
        $this->assertTrue($describedby->hasUrl());
        $this->assertEquals('http://example.com/api/help/resource', $describedby->getUrl());

        $children = $properties->get('children');
        $this->assertTrue($children->hasRoute());
        $this->assertEquals('resource/children', $children->getRoute());
    }

    /**
     * Test that the jsonLD metadata route params config allows callables.
     *
     * All callables should be passed the object being used for entity creation.
     * If closure binding is supported, any closures should be bound to that
     * object.
     *
     * The return value should be used as the route param for the property (in
     * place of the callable).
     */
    public function testRouteParamsAllowsCallable()
    {
        $object = new TestAsset\Entity('foo', 'Foo');

        $callback = $this->getMock('stdClass', ['callback']);
        $callback->expects($this->atLeastOnce())
                 ->method('callback')
                 ->with($this->equalTo($object))
                 ->will($this->returnValue('callback-param'));

        $test = $this;

        $metadata = new MetadataMap([
            'ZFTest\JsonLD\Plugin\TestAsset\Entity' => [
                'hydrator'     => 'Zend\Hydrator\ObjectProperty',
                'route_name'   => 'hostname/resource',
                'route_params' => [
                    'test-1' => [$callback, 'callback'],
                    'test-2' => function ($expected) use ($object, $test) {
                        $test->assertSame($expected, $object);
                        if (version_compare(PHP_VERSION, '5.4.0') >= 0) {
                            $test->assertSame($object, $this);
                        }

                        return 'closure-param';
                    },
                ],
            ],
        ]);

        $resourceFactory = $this->getResourceFactory($metadata);

        $entity = $resourceFactory->createEntityFromMetadata(
            $object,
            $metadata->get('ZFTest\JsonLD\Plugin\TestAsset\Entity')
        );

        $this->assertInstanceof('ZF\JsonLD\Entity', $entity);

        $properties = $entity->getProperties();
        $this->assertTrue($properties->has('@id'));

        $idProperty = $properties->get('@id');
        $params = $idProperty->getRouteParams();

        $this->assertArrayHasKey('test-1', $params);
        $this->assertEquals('callback-param', $params['test-1']);

        $this->assertArrayHasKey('test-2', $params);
        $this->assertEquals('closure-param', $params['test-2']);
    }

    /**
     * @group 79
     */
    public function testInjectsPropertiesFromMetadataWhenCreatingCollection()
    {
        $set = new TestAsset\Collection([
            (object) ['id' => 'foo', 'name' => 'foo'],
            (object) ['id' => 'bar', 'name' => 'bar'],
            (object) ['id' => 'baz', 'name' => 'baz'],
        ]);

        $metadata = new MetadataMap([
            'ZFTest\JsonLD\Plugin\TestAsset\Collection' => [
                'is_collection'       => true,
                'route_name'          => 'hostname/contacts',
                'entity_route_name'   => 'hostname/embedded',
                'properties'               => [
                    [
                        'key' => 'describedby',
                        'url' => 'http://example.com/api/help/collection',
                    ],
                ],
            ],
        ]);

        $resourceFactory = $this->getResourceFactory($metadata);

        $collection = $resourceFactory->createCollectionFromMetadata(
            $set,
            $metadata->get('ZFTest\JsonLD\Plugin\TestAsset\Collection')
        );

        $this->assertInstanceof('ZF\JsonLD\Collection', $collection);
        $properties = $collection->getProperties();
        $this->assertTrue($properties->has('describedby'));
        $property = $properties->get('describedby');
        $this->assertTrue($property->hasUrl());
        $this->assertEquals('http://example.com/api/help/collection', $property->getUrl());
    }

    private function getResourceFactory(MetadataMap $metadata)
    {
        $hydratorPluginManager = new HydratorPluginManager();
        $entityHydratorManager = new EntityHydratorManager($hydratorPluginManager, $metadata);
        $entityExtractor       = new EntityExtractor($entityHydratorManager);

        return new ResourceFactory($entityHydratorManager, $entityExtractor);
    }
}
