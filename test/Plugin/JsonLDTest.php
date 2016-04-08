<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZFTest\JsonLD\Plugin;

use PHPUnit_Framework_TestCase as TestCase;
use ReflectionObject;
use Zend\Mvc\Router\Http\TreeRouteStack;
use Zend\Mvc\Router\Http\Segment;
use Zend\Mvc\MvcEvent;
use Zend\Paginator\Adapter\ArrayAdapter as ArrayPaginator;
use Zend\Paginator\Paginator;
use Zend\Uri\Http;
use Zend\Hydrator;
use Zend\View\Helper\Url as UrlHelper;
use Zend\View\Helper\ServerUrl as ServerUrlHelper;
use ZF\JsonLD\Collection;
use ZF\JsonLD\Entity;
use ZF\JsonLD\Extractor\PropertyCollectionExtractor;
use ZF\JsonLD\Extractor\PropertyExtractor;
use ZF\JsonLD\Property\Property;
use ZF\JsonLD\Property\PropertyCollection;
use ZF\JsonLD\Metadata\MetadataMap;
use ZF\JsonLD\Plugin\JsonLD as JsonLDHelper;
use ZFTest\JsonLD\TestAsset as JsonLDTestAsset;

/**
 * @subpackage UnitTest
 */
class JsonLDTest extends TestCase
{
    /**
     * @var JsonLDHelper
     */
    protected $plugin;

    public function setUp()
    {
        $this->router = $router = new TreeRouteStack();
        $route = new Segment('/resource[/[:id]]');
        $router->addRoute('resource', $route);
        $route2 = new Segment('/help');
        $router->addRoute('docs', $route2);
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
                    'may_terminate' => true,
                    'child_routes' => [
                        'children' => [
                            'type' => 'literal',
                            'options' => [
                                'route' => '/children',
                            ],
                        ],
                    ],
                ],
                'users' => [
                    'type' => 'segment',
                    'options' => [
                        'route' => '/users[/:id]'
                    ]
                ],
                'contacts' => [
                    'type' => 'segment',
                    'options' => [
                        'route' => '/contacts[/:id]'
                    ]
                ],
                'embedded' => [
                    'type' => 'segment',
                    'options' => [
                        'route' => '/embedded[/:id]'
                    ]
                ],
                'embedded_custom' => [
                    'type' => 'segment',
                    'options' => [
                        'route' => '/embedded_custom[/:custom_id]'
                    ]
                ],
            ]
        ]);

        $this->event = $event = new MvcEvent();
        $event->setRouter($router);
        $router->setRequestUri(new Http('http://localhost.localdomain/resource'));

        $controller = $this->controller = $this->getMock('Zend\Mvc\Controller\AbstractRestfulController');
        $controller->expects($this->any())
            ->method('getEvent')
            ->will($this->returnValue($event));

        $this->urlHelper = $urlHelper = new UrlHelper();
        $urlHelper->setRouter($router);

        $this->serverUrlHelper = $serverUrlHelper = new ServerUrlHelper();
        $serverUrlHelper->setScheme('http');
        $serverUrlHelper->setHost('localhost.localdomain');

        $this->plugin = $plugin = new JsonLDHelper();
        $plugin->setController($controller);
        $plugin->setUrlHelper($urlHelper);
        $plugin->setServerUrlHelper($serverUrlHelper);

        $propertyExtractor = new PropertyExtractor($serverUrlHelper, $urlHelper);
        $propertyCollectionExtractor = new PropertyCollectionExtractor($propertyExtractor);
        $plugin->setPropertyCollectionExtractor($propertyCollectionExtractor);
    }

    public function assertRelationalPropertyContains($match, $relation, $entity)
    {
        $this->assertInternalType('array', $entity);
        $this->assertArrayHasKey($relation, $entity);
        $property = $entity[$relation];
        $this->assertInternalType('string', $property);
        $this->assertContains($match, $property);
    }

    public function testCreatePropertySkipServerUrlHelperIfSchemeExists()
    {
        $url = $this->plugin->createProperty('hostname/resource');
        $this->assertEquals('http://localhost.localdomain/resource', $url);
    }

    public function testPropertyCreationWithoutIdCreatesFullyQualifiedProperty()
    {
        $url = $this->plugin->createProperty('resource');
        $this->assertEquals('http://localhost.localdomain/resource', $url);
    }

    public function testPropertyCreationWithIdCreatesFullyQualifiedProperty()
    {
        $url = $this->plugin->createProperty('resource', 123);
        $this->assertEquals('http://localhost.localdomain/resource/123', $url);
    }

    public function testPropertyCreationFromEntity()
    {
        $self = new Property('self');
        $self->setRoute('resource', ['id' => 123]);
        $docs = new Property('describedby');
        $docs->setRoute('docs');
        $entity = new Entity([], 123);
        $entity->getProperties()->add($self)->add($docs);
        $properties = $this->plugin->fromResource($entity);

        $this->assertInternalType('array', $properties);
        $this->assertArrayHasKey('self', $properties, var_export($properties, 1));
        $this->assertArrayHasKey('describedby', $properties, var_export($properties, 1));

        $selfProperty = $properties['self'];
        $this->assertInternalType('string', $selfProperty);
        $this->assertEquals('http://localhost.localdomain/resource/123', $selfProperty);

        $docsProperty = $properties['describedby'];
        $this->assertInternalType('string', $docsProperty);
        $this->assertEquals('http://localhost.localdomain/help', $docsProperty);
    }

    public function testRendersEmbeddedCollectionsInsideEntities()
    {
        $collection = new Collection(
            [
                (object) ['id' => 'http://foo.bar/foo', 'name' => 'foo'],
                (object) ['id' => 'http://foo.bar/bar', 'name' => 'bar'],
                (object) ['id' => 'http://foo.bar/baz', 'name' => 'baz'],
            ],
            'hostname/contacts'
        );
        $entity = new Entity(
            (object) [
                'id'       => 'http://foo.bar/123',
                'contacts' => $collection,
            ],
            'user'
        );
        $self = new Property('foo');
        $self->setRoute('hostname/users', ['id' => 'user']);
        $entity->getProperties()->add($self);

        $rendered = $this->plugin->renderEntity($entity);
        $this->assertRelationalPropertyContains('/users/', 'foo', $rendered);

        $this->assertArrayHasKey('contacts', $rendered);
        $contacts = $rendered['contacts'];
        $this->assertInternalType('array', $contacts);
        $this->assertEquals(3, count($contacts));
        foreach ($contacts as $contact) {
            $this->assertInternalType('array', $contact);
            $this->assertRelationalPropertyContains('http://foo.bar', 'id', $contact);
        }
    }

    public function testRendersEmbeddedEntitiesInsideEntitiesBasedOnMetadataMap()
    {
        $object = new TestAsset\Entity('foo', 'Foo');
        $object->first_child  = new TestAsset\EmbeddedEntity('bar', 'Bar');
        $object->second_child = new TestAsset\EmbeddedEntityWithCustomIdentifier('baz', 'Baz');
        $entity = new Entity($object, 'foo');
        $self = new Property('id');
        $self->setRoute('hostname/resource', ['id' => 'foo']);
        $entity->getProperties()->add($self);

        $metadata = new MetadataMap([
            'ZFTest\JsonLD\Plugin\TestAsset\Entity' => [
                'hydrator'   => 'Zend\Hydrator\ObjectProperty',
                'route_name' => 'hostname/resource',
                'route_identifier_name' => 'id',
                'entity_identifier_name' => 'id',
            ],
            'ZFTest\JsonLD\Plugin\TestAsset\EmbeddedEntity' => [
                'hydrator' => 'Zend\Hydrator\ObjectProperty',
                'route'    => 'hostname/embedded',
                'route_identifier_name' => 'id',
                'entity_identifier_name' => 'id',
            ],
            'ZFTest\JsonLD\Plugin\TestAsset\EmbeddedEntityWithCustomIdentifier' => [
                'hydrator'        => 'Zend\Hydrator\ObjectProperty',
                'route'           => 'hostname/embedded_custom',
                'route_identifier_name' => 'custom_id',
                'entity_identifier_name' => 'custom_id',
            ],
        ]);

        $this->plugin->setMetadataMap($metadata);

        $rendered = $this->plugin->renderEntity($entity);
        $this->assertRelationalPropertyContains('/resource/foo', 'id', $rendered);

        $this->assertArrayHasKey('first_child', $rendered);
        $this->assertArrayHasKey('second_child', $rendered);

        $first = $rendered['first_child'];
        $this->assertInternalType('array', $first);
        $this->assertRelationalPropertyContains('/embedded/bar', 'id', $first);

        $second = $rendered['second_child'];
        $this->assertInternalType('array', $second);
        $this->assertRelationalPropertyContains('/embedded_custom/baz', 'id', $second);
    }

    public function testMetadataMapLooksForParentClasses()
    {
        $object = new TestAsset\Entity('foo', 'Foo');
        $object->first_child  = new TestAsset\EmbeddedProxyEntity('bar', 'Bar');
        $object->second_child = new TestAsset\EmbeddedProxyEntityWithCustomIdentifier('baz', 'Baz');
        $entity = new Entity($object, 'foo');
        $self = new Property('id');
        $self->setRoute('hostname/resource', ['id' => 'foo']);
        $entity->getProperties()->add($self);

        $metadata = new MetadataMap([
            'ZFTest\JsonLD\Plugin\TestAsset\Entity' => [
                'hydrator'   => 'Zend\Hydrator\ObjectProperty',
                'route_name' => 'hostname/resource',
                'route_identifier_name' => 'id',
                'entity_identifier_name' => 'id',
            ],
            'ZFTest\JsonLD\Plugin\TestAsset\EmbeddedEntity' => [
                'hydrator' => 'Zend\Hydrator\ObjectProperty',
                'route'    => 'hostname/embedded',
                'route_identifier_name' => 'id',
                'entity_identifier_name' => 'id',
            ],
            'ZFTest\JsonLD\Plugin\TestAsset\EmbeddedEntityWithCustomIdentifier' => [
                'hydrator'        => 'Zend\Hydrator\ObjectProperty',
                'route'           => 'hostname/embedded_custom',
                'route_identifier_name' => 'custom_id',
                'entity_identifier_name' => 'custom_id',
            ],
        ]);

        $this->plugin->setMetadataMap($metadata);

        $rendered = $this->plugin->renderEntity($entity);
        $this->assertRelationalPropertyContains('/resource/foo', 'id', $rendered);

        $this->assertArrayHasKey('first_child', $rendered);
        $this->assertArrayHasKey('second_child', $rendered);

        $first = $rendered['first_child'];
        $this->assertInternalType('array', $first);
        $this->assertRelationalPropertyContains('/embedded/bar', 'id', $first);

        $second = $rendered['second_child'];
        $this->assertInternalType('array', $second);
        $this->assertRelationalPropertyContains('/embedded_custom/baz', 'id', $second);
    }

    public function testRendersJsonSerializableObjectUsingJsonserializeMethod()
    {
        $object   = new TestAsset\JsonSerializableEntity('foo', 'Foo');
        $entity   = new Entity($object, 'foo');

        $rendered = $this->plugin->renderEntity($entity);

        $this->assertArrayHasKey('id', $rendered);
        $this->assertArrayNotHasKey('name', $rendered);
    }

    public function testRendersEmbeddedCollectionsInsideEntitiesBasedOnMetadataMap()
    {
        $collection = new TestAsset\Collection([
            (object) ['id' => 'foo', 'name' => 'foo'],
            (object) ['id' => 'bar', 'name' => 'bar'],
            (object) ['id' => 'baz', 'name' => 'baz'],
        ]);

        $metadata = new MetadataMap([
            'ZFTest\JsonLD\Plugin\TestAsset\Collection' => [
                'is_collection'       => true,
                'collection_name'     => 'collection', // should be overridden
                'route_name'          => 'hostname/contacts',
                'entity_route_name'   => 'hostname/embedded',
                'route_identifier_name' => 'id',
                'entity_identifier_name' => 'id',
            ],
        ]);

        $this->plugin->setMetadataMap($metadata);

        $entity = new Entity(
            (object) [
                'id'       => 'user',
                'contacts' => $collection,
            ],
            'user'
        );
        $self = new Property('self');
        $self->setRoute('hostname/users', ['id' => 'user']);
        $entity->getProperties()->add($self);

        $rendered = $this->plugin->renderEntity($entity);

        $this->assertRelationalPropertyContains('/users/', 'self', $rendered);

        $this->assertArrayHasKey('contacts', $rendered);
        $contacts = $rendered['contacts'];
        $this->assertInternalType('array', $contacts);
        $this->assertEquals(3, count($contacts));

        foreach ($contacts as $contact) {
            $this->assertInternalType('array', $contact);
            $this->assertArrayHasKey('id', $contact);
            $this->assertRelationalPropertyContains($contact['id'], 'id', $contact);
        }
    }

    public function testRendersEmbeddedCollectionsInsideCollectionsBasedOnMetadataMap()
    {
        $childCollection = new TestAsset\Collection([
            (object) ['id' => 'foo', 'name' => 'foo'],
            (object) ['id' => 'bar', 'name' => 'bar'],
            (object) ['id' => 'baz', 'name' => 'baz'],
        ]);
        $entity = new TestAsset\Entity('spock', 'Spock');
        $entity->first_child = $childCollection;

        $metadata = new MetadataMap([
            'ZFTest\JsonLD\Plugin\TestAsset\Collection' => [
                'is_collection'  => true,
                'route'          => 'hostname/contacts',
                'entity_route'   => 'hostname/embedded',
                'route_identifier_name' => 'id',
                'entity_identifier_name' => 'id',
            ],
            'ZFTest\JsonLD\Plugin\TestAsset\Entity' => [
                'hydrator'   => 'Zend\Hydrator\ObjectProperty',
                'route_name' => 'hostname/resource',
                'route_identifier_name' => 'id',
                'entity_identifier_name' => 'id',
            ],
        ]);

        $this->plugin->setMetadataMap($metadata);

        $collection = new Collection([$entity], 'hostname/resource');
        $idProperty = new Property('id');
        $idProperty->setRoute('hostname/resource');
        $collection->getProperties()->add($idProperty);
        $collection->setCollectionName('resources');

        $rendered = $this->plugin->renderCollection($collection);

        $this->assertRelationalPropertyContains('/resource', 'id', $rendered);

        $this->assertArrayHasKey('resources', $rendered);
        $member = $rendered['resources'];
        $this->assertInternalType('array', $member);
        $this->assertEquals(1, count($member));

        $resource = array_shift($member);
        $this->assertInternalType('array', $resource);
        $this->assertArrayHasKey('first_child', $resource);
        $this->assertInternalType('array', $resource['first_child']);
        $this->assertEquals(3, count($resource['first_child']));

        foreach ($resource['first_child'] as $contact) {
            $this->assertInternalType('array', $contact);
            $this->assertArrayHasKey('id', $contact);
            $this->assertRelationalPropertyContains($contact['id'], 'id', $contact);
        }
    }

    // @codingStandardsIgnoreStart
    public function testDoesNotRenderEmbeddedEntitiesInsideCollectionsBasedOnMetadataMapAndRenderEmbeddedEntitiesAsFalse()
    {
        $entity = new TestAsset\Entity('spock', 'Spock');
        $entity->first_child  = new TestAsset\EmbeddedEntity('bar', 'Bar');
        $entity->second_child = new TestAsset\EmbeddedEntityWithCustomIdentifier('baz', 'Baz');

        $metadata = new MetadataMap([
            'ZFTest\JsonLD\Plugin\TestAsset\EmbeddedEntity' => [
                'hydrator' => 'Zend\Hydrator\ObjectProperty',
                'route'    => 'hostname/embedded',
            ],
            'ZFTest\JsonLD\Plugin\TestAsset\EmbeddedEntityWithCustomIdentifier' => [
                'hydrator'        => 'Zend\Hydrator\ObjectProperty',
                'route'           => 'hostname/embedded_custom',
                'route_identifier_name' => 'custom_id',
                'entity_identifier_name' => 'custom_id',
            ],
            'ZFTest\JsonLD\Plugin\TestAsset\Collection' => [
                'is_collection'  => true,
                'route'          => 'hostname/contacts',
                'entity_route'   => 'hostname/embedded',
            ],
            'ZFTest\JsonLD\Plugin\TestAsset\Entity' => [
                'hydrator'   => 'Zend\Hydrator\ObjectProperty',
                'route_name' => 'hostname/resource',
            ],
        ]);

        $this->plugin->setMetadataMap($metadata);
        $this->plugin->setRenderEmbeddedEntities(false);

        $collection = new Collection([$entity], 'hostname/resource');
        $self = new Property('id');
        $self->setRoute('hostname/resource');
        $collection->getProperties()->add($self);
        $collection->setCollectionName('resources');

        $rendered = $this->plugin->renderCollection($collection);

        $this->assertRelationalPropertyContains('/resource', 'id', $rendered);

        $this->assertArrayHasKey('resources', $rendered);
        $embed = $rendered['resources'];
        $this->assertInternalType('array', $embed);
        $this->assertEquals(1, count($embed));

        $resource = array_shift($embed);
        $this->assertInternalType('array', $resource);
        $this->assertArrayHasKey('first_child', $resource);
        $this->assertArrayHasKey('second_child', $resource);

        $this->assertInternalType('array', $resource['first_child']);
        $this->assertArrayHasKey('id', $resource['first_child']);

        $this->assertInternalType('array', $resource['second_child']);
        $this->assertArrayHasKey('id', $resource['second_child']);

    }
    // @codingStandardsIgnoreEnd

    public function testWillNotAllowInjectingASelfRelationMultipleTimes()
    {
        $entity = new Entity([
            'id'  => 1,
            'foo' => 'bar',
        ], 1);
        $properties = $entity->getProperties();

        $this->assertFalse($properties->has('id'));

        $this->plugin->injectIDProperty($entity, 'hostname/resource');

        $this->assertTrue($properties->has('id'));
        $property = $properties->get('id');
        $this->assertInstanceof('ZF\JsonLD\Property\Property', $property);

        $this->plugin->injectIDProperty($entity, 'hostname/resource');
        $this->assertTrue($properties->has('id'));
        $property = $properties->get('id');
        $this->assertInstanceof('ZF\JsonLD\Property\Property', $property);
    }

    public function testEntityPropertiesCanBeProperties()
    {
        $embeddedProperty = new Property('@context');
        $embeddedProperty->setRoute('hostname/contacts', ['id' => 'bar']);

        $properties = [
            'id' => '10',
            '@context' => $embeddedProperty,
        ];

        $entity = new Entity((object) $properties, 'foo');

        $rendered = $this->plugin->renderEntity($entity);

        $this->assertArrayHasKey('@context', $rendered);
        $this->assertEquals('http://localhost.localdomain/contacts/bar', $rendered['@context']);
    }

    public function testEntityPropertyPropertiesUseHref()
    {
        $property1 = new Property('property1');
        $property1->setUrl('property1');

        $property2 = new Property('property2');
        $property2->setUrl('property2');

        $properties = [
            'id' => '10',
            'bar' => $property1,
            'baz' => $property2,
        ];

        $entity = new Entity((object) $properties, 'foo');

        $rendered = $this->plugin->renderEntity($entity);

        $this->assertArrayHasKey('property1', $rendered);
        $this->assertArrayHasKey('property2', $rendered);
    }

    public function testEntityPropertiesCanBePropertyCollections()
    {
        $property = new Property('embeddedProperty');
        $property->setRoute('hostname/contacts', ['id' => 'bar']);

        //simple property
        $collection = new PropertyCollection();
        $collection->add($property);

        //array of properties
        $propertyArray = new Property('arrayProperty');
        $propertyArray->setRoute('hostname/contacts', ['id' => 'bar']);
        $collection->add($propertyArray);

        $propertyArray = new Property('arrayProperty');
        $propertyArray->setRoute('hostname/contacts', ['id' => 'baz']);
        $collection->add($propertyArray);

        $properties = [
            'id' => '10',
            'properties' => $collection,
        ];

        $entity = new Entity((object) $properties, 'foo');

        $rendered = $this->plugin->renderEntity($entity);

        $this->assertArrayHasKey('embeddedProperty', $rendered);
        $this->assertEquals('http://localhost.localdomain/contacts/bar', $rendered['embeddedProperty']);

        $this->assertArrayHasKey('arrayProperty', $rendered);
        $this->assertCount(2, $rendered['arrayProperty']);
    }

    /**
     * @group 71
     */
    public function testRenderingEmbeddedEntityEmbedsEntity()
    {
        $embedded = new Entity((object) ['id' => 'foo', 'name' => 'foo'], 'foo');
        $self = new Property('id');
        $self->setRoute('hostname/contacts', ['id' => 'foo']);
        $embedded->getProperties()->add($self);

        $entity = new Entity((object) ['id' => 'user', 'contact' => $embedded], 'user');
        $self = new Property('id');
        $self->setRoute('hostname/users', ['id' => 'user']);
        $entity->getProperties()->add($self);

        $rendered = $this->plugin->renderEntity($entity);

        $this->assertRelationalPropertyContains('/users/user', 'id', $rendered);
        $this->assertArrayHasKey('contact', $rendered);
        $this->assertInternalType('array', $rendered['contact']);
        $this->assertRelationalPropertyContains('/contacts/foo', 'id', $rendered['contact']);
    }

    /**
     * @group 71
     */
    public function testRenderingCollectionRendersAllPropertiesInEmbeddedEntities()
    {
        $embedded = new Entity((object) ['id' => 'foo', 'name' => 'foo'], 'foo');
        $properties = $embedded->getProperties();
        $self = new Property('id');
        $self->setRoute('hostname/users', ['id' => 'foo']);
        $properties->add($self);
        $phones = new Property('phones');
        $phones->setUrl('http://localhost.localdomain/users/foo/phones');
        $properties->add($phones);

        $collection = new Collection([$embedded]);
        $collection->setCollectionName('users');
        $self = new Property('id');
        $self->setRoute('hostname/users');
        $collection->getProperties()->add($self);

        $rendered = $this->plugin->renderCollection($collection);

        $this->assertRelationalPropertyContains('/users', 'id', $rendered);
        $this->assertArrayHasKey('users', $rendered);
        $this->assertInternalType('array', $rendered['users']);

        $users = $rendered['users'];
        $this->assertInternalType('array', $users);
        $user = array_shift($users);

        $this->assertRelationalPropertyContains('/users/foo', 'id', $user);
        $this->assertRelationalPropertyContains('/users/foo/phones', 'phones', $user);
    }

    public function testEntitiesFromCollectionCanUseHydratorSetInMetadataMap()
    {
        $object   = new TestAsset\EntityWithProtectedProperties('foo', 'Foo');
        $entity   = new Entity($object, 'foo');

        $metadata = new MetadataMap([
            'ZFTest\JsonLD\Plugin\TestAsset\EntityWithProtectedProperties' => [
                'hydrator'   => 'ArraySerializable',
                'route_name' => 'hostname/resource',
            ],
        ]);

        $collection = new Collection([$entity]);
        $collection->setCollectionName('resource');
        $collection->setCollectionRoute('hostname/resource');

        $this->plugin->setMetadataMap($metadata);

        $test = $this->plugin->renderCollection($collection);

        $this->assertInternalType('array', $test);
        $this->assertArrayHasKey('resource', $test);
        $this->assertInternalType('array', $test['resource']);

        $resources = $test['resource'];
        $testResource = array_shift($resources);
        $this->assertInternalType('array', $testResource);
        $this->assertArrayHasKey('id', $testResource);
        $this->assertArrayHasKey('name', $testResource);
    }

    /**
     * @group 47
     */
    public function testRetainsPropertiesInjectedViaMetadataDuringCreateEntity()
    {
        $object = new TestAsset\Entity('foo', 'Foo');
        $entity = new Entity($object, 'foo');

        $metadata = new MetadataMap([
            'ZFTest\JsonLD\Plugin\TestAsset\Entity' => [
                'hydrator'   => 'Zend\Hydrator\ObjectProperty',
                'route_name' => 'hostname/resource',
                'properties' => [
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

        $this->plugin->setMetadataMap($metadata);
        $entity = $this->plugin->createEntity($object, 'hostname/resource', 'id');
        $this->assertInstanceof('ZF\JsonLD\Entity', $entity);
        $properties = $entity->getProperties();
        $this->assertTrue($properties->has('describedby'), 'Missing describedby property');
        $this->assertTrue($properties->has('children'), 'Missing children property');

        $describedby = $properties->get('describedby');
        $this->assertTrue($describedby->hasUrl());
        $this->assertEquals('http://example.com/api/help/resource', $describedby->getUrl());

        $children = $properties->get('children');
        $this->assertTrue($children->hasRoute());
        $this->assertEquals('resource/children', $children->getRoute());
    }

    /**
     * @group 79
     */
    public function testRenderEntityTriggersEvents()
    {
        $entity = new Entity(
            (object) [
                'id'   => 'user',
                'name' => 'matthew',
            ],
            'user'
        );
        $idProperty = new Property('id');
        $idProperty->setRoute('hostname/users', ['id' => 'user']);
        $entity->getProperties()->add($idProperty);

        $this->plugin->getEventManager()->attach('renderEntity', function ($e) {
            $entity = $e->getParam('entity');
            $entity->getProperties()->get('id')->setRouteParams(['id' => 'matthew']);
        });

        $rendered = $this->plugin->renderEntity($entity);
        $this->assertContains('/users/matthew', $rendered['id']);
    }

    /**
     * @group 79
     */
    public function testRenderCollectionTriggersEvents()
    {
        $collection = new Collection(
            [
                (object) ['id' => 'foo', 'name' => 'foo'],
                (object) ['id' => 'bar', 'name' => 'bar'],
                (object) ['id' => 'baz', 'name' => 'baz'],
            ],
            'hostname/contacts'
        );
        $self = new Property('self');
        $self->setRoute('hostname/contacts');
        $collection->getProperties()->add($self);
        $collection->setCollectionName('resources');

        $this->plugin->getEventManager()->attach('renderCollection', function ($e) {
            $collection = $e->getParam('collection');
            $collection->setAttributes(['injected' => true]);
        });

        $rendered = $this->plugin->renderCollection($collection);
        $this->assertArrayHasKey('injected', $rendered);
        $this->assertTrue($rendered['injected']);

        $that = $this;
        $this->plugin->getEventManager()->attach('renderCollection.post', function ($e) use ($that) {
            $collection = $e->getParam('collection');
            $payload = $e->getParam('payload');

            $that->assertInstanceOf('ArrayObject', $payload);
            $that->assertInstanceOf('ZF\JsonLD\Collection', $collection);

            $payload['_post'] = true;
        });

        $rendered = $this->plugin->renderCollection($collection);
        $this->assertArrayHasKey('_post', $rendered);
        $this->assertTrue($rendered['_post']);
    }

    public function testFromPropertyShouldUsePropertyExtractor()
    {
        $extraction = true;

        $propertyExtractor = $this->getMockBuilder('ZF\JsonLD\Extractor\PropertyExtractor')
            ->disableOriginalConstructor()
            ->getMock();
        $propertyExtractor
            ->expects($this->once())
            ->method('extract')
            ->will($this->returnValue($extraction));

        $propertyCollectionExtractor = $this->getMockBuilder('ZF\JsonLD\Extractor\PropertyCollectionExtractor')
            ->disableOriginalConstructor()
            ->getMock();
        $propertyCollectionExtractor
            ->expects($this->once())
            ->method('getPropertyExtractor')
            ->will($this->returnValue($propertyExtractor));

        $this->plugin->setPropertyCollectionExtractor($propertyCollectionExtractor);

        $property = new Property('foo');

        $result = $this->plugin->fromProperty($property);

        $this->assertEquals($extraction, $result);
    }

    public function testFromPropertyCollectionShouldUsePropertyCollectionExtractor()
    {
        $extraction = true;

        $propertyCollectionExtractor = $this->getMockBuilder('ZF\JsonLD\Extractor\PropertyCollectionExtractor')
            ->disableOriginalConstructor()
            ->getMock();
        $propertyCollectionExtractor
            ->expects($this->once())
            ->method('extract')
            ->will($this->returnValue($extraction));

        $this->plugin->setPropertyCollectionExtractor($propertyCollectionExtractor);

        $propertyCollection = new PropertyCollection();

        $result = $this->plugin->fromPropertyCollection($propertyCollection);

        $this->assertEquals($extraction, $result);
    }

    public function testCreateCollectionShouldUseCollectionRouteMetadataWhenInjectingSelfProperty()
    {
        $collection = new Collection(['foo' => 'bar']);
        $collection->setCollectionRoute('hostname/resource');
        $collection->setCollectionRouteOptions([
            'query' => [
                'version' => 2,
            ],
        ]);
        $result = $this->plugin->createCollection($collection);
        $properties  = $result->getProperties();
        $idProperty = $properties->get('id');
        $this->assertEquals([
            'query' => [
                'version' => 2,
            ],
        ], $idProperty->getRouteOptions());
    }

    public function testRenderingCollectionUsesCollectionNameFromMetadataMap()
    {
        $object1 = new TestAsset\Entity('foo', 'Foo');
        $object2 = new TestAsset\Entity('bar', 'Bar');
        $object3 = new TestAsset\Entity('baz', 'Baz');

        $collection = new TestAsset\Collection([
            $object1,
            $object2,
            $object3,
        ]);

        $metadata = new MetadataMap([
            'ZFTest\JsonLD\Plugin\TestAsset\Entity' => [
                'hydrator'   => 'Zend\Hydrator\ObjectProperty',
                'route_name' => 'hostname/resource',
                'route_identifier_name' => 'id',
                'entity_identifier_name' => 'id',
            ],
            'ZFTest\JsonLD\Plugin\TestAsset\Collection' => [
                'is_collection'       => true,
                'collection_name'     => 'collection',
                'route_name'          => 'hostname/contacts',
                'entity_route_name'   => 'hostname/embedded',
            ],
        ]);

        $this->plugin->setMetadataMap($metadata);

        $jsonLDCollection = $this->plugin->createCollection($collection);
        $rendered = $this->plugin->renderCollection($jsonLDCollection);

        $this->assertRelationalPropertyContains('/contacts', 'id', $rendered);
        $this->assertArrayHasKey('collection', $rendered);
        $this->assertInternalType('array', $rendered['collection']);

        $renderedCollection = $rendered['collection'];

        foreach ($renderedCollection as $entity) {
            $this->assertRelationalPropertyContains('/resource/', 'id', $entity);
        }
    }

    /**
     * @group 14
     */
    public function testRenderingPaginatorCollectionRendersPaginationAttributes()
    {
        $set = [];
        for ($id = 1; $id <= 100; $id += 1) {
            $entity = new Entity((object) ['id' => $id, 'name' => 'foo'], 'foo');
            $properties = $entity->getProperties();
            $self = new Property('self');
            $self->setRoute('hostname/users', ['id' => $id]);
            $properties->add($self);
            $set[] = $entity;
        }

        $paginator  = new Paginator(new ArrayPaginator($set));
        $collection = new Collection($paginator);
        $collection->setCollectionName('users');
        $collection->setCollectionRoute('hostname/users');
        $collection->setPage(3);
        $collection->setPageSize(10);

        $rendered = $this->plugin->renderCollection($collection);
        $expected = [
            'id',
            'view',
            'users',
            'totalItems',
            '@context',
            '@type',
        ];
        $expectedView = [
            'id',
            'firstPage',
            'lastPage',
            'previousPage',
            'nextPage',
            '@type',
            'itemsPerPage',
        ];
        $this->assertEquals($expected, array_keys($rendered));
        $this->assertArrayHasKey('view', $rendered);
        $this->assertEquals($expectedView, array_keys($rendered['view']));
        $this->assertEquals(100, $rendered['totalItems']);
        $this->assertEquals(3,  filter_var($rendered['view']['id'], FILTER_SANITIZE_NUMBER_INT));
        $this->assertEquals(10, filter_var($rendered['view']['lastPage'], FILTER_SANITIZE_NUMBER_INT));
        $this->assertEquals(10, $rendered['view']['itemsPerPage']);
        $this->assertNotContains('page=1', $rendered['view']['firstPage']);
    }

    /**
     * @group 14
     */
    public function testRenderingNonPaginatorCollectionRendersCountOfTotalItems()
    {
        $embedded = new Entity((object) ['id' => 'foo', 'name' => 'foo'], 'foo');
        $properties = $embedded->getProperties();
        $self = new Property('self');
        $self->setRoute('hostname/users', ['id' => 'foo']);
        $properties->add($self);

        $collection = new Collection([$embedded]);
        $collection->setCollectionName('users');
        $self = new Property('id');
        $self->setRoute('hostname/users');
        $collection->getProperties()->add($self);

        $rendered = $this->plugin->renderCollection($collection);

        $expectedKeys = ['id', 'users', 'totalItems', '@context', '@type'];
        $this->assertEquals($expectedKeys, array_keys($rendered));
    }

    /**
     * @group 33
     */
    public function testCreateEntityShouldNotSerializeEntity()
    {
        $metadata = new MetadataMap([
            'ZFTest\JsonLD\Plugin\TestAsset\Entity' => [
                'hydrator'   => 'Zend\Hydrator\ObjectProperty',
                'route_name' => 'hostname/resource',
                'route_identifier_name' => 'id',
                'entity_identifier_name' => 'id',
            ],
        ]);
        $this->plugin->setMetadataMap($metadata);

        $foo = new TestAsset\Entity('foo', 'Foo Bar');

        $entity = $this->plugin->createEntity($foo, 'api.foo', 'foo_id');
        $this->assertInstanceOf('ZF\JsonLD\Entity', $entity);
        $this->assertSame($foo, $entity->entity);
    }

    /**
     * @group 39
     */
    public function testCreateEntityPassesNullValueForIdentifierIfNotDiscovered()
    {
        $entity = ['foo' => 'bar'];
        $jsonLD    = $this->plugin->createEntity($entity, 'api.foo', 'foo_id');
        $this->assertInstanceOf('ZF\JsonLD\Entity', $jsonLD);
        $this->assertEquals($entity, $jsonLD->entity);
        $this->assertNull($jsonLD->id);

        $properties = $jsonLD->getProperties();
        $this->assertTrue($properties->has('id'));
        $property = $properties->get('id');
        $params = $property->getRouteParams();
        $this->assertEquals([], $params);
    }

    /**
     * @param Entity      $entity
     * @param MetadataMap $metadataMap
     * @param array       $expectedResult
     * @param array       $exception
     *
     * @dataProvider renderEntityMaxDepthProvider
     */
    public function testRenderEntityMaxDepth($entity, $metadataMap, $expectedResult, $exception = null)
    {
        $this->plugin->setMetadataMap($metadataMap);

        if ($exception) {
            $this->setExpectedException($exception['class'], $exception['message']);
        }

        $result = $this->plugin->renderEntity($entity);

        $this->assertEquals($expectedResult, $result);
    }

    public function renderEntityMaxDepthProvider()
    {
        return [
            /**
             * [
             *     $entity,
             *     $metadataMap,
             *     $expectedResult,
             *     $exception,
             * ]
             */
            [
                $this->createNestedEntity(),
                $this->createNestedMetadataMap(),
                null,
                [
                    'class'   => 'ZF\JsonLD\Exception\CircularReferenceException',
                    'message' => 'Circular reference detected in \'ZFTest\JsonLD\Plugin\TestAsset\Entity\'',
                ]
            ],
            [
                $this->createNestedEntity(),
                $this->createNestedMetadataMap(1),
                [
                    'name' => 'Foo',
                    'second_child' => null,
                    'first_child' => [
                        'parent' => [
                            'id' => 'http://localhost.localdomain/resource/foo',
                        ],
                        'id' => 'http://localhost.localdomain/embedded/bar',
                    ],
                    'id' => 'http://localhost.localdomain/resource/foo',
                ]
            ],
            [
                $this->createNestedEntity(),
                $this->createNestedMetadataMap(2),
                [
                    'name' => 'Foo',
                    'second_child' => null,
                    'first_child' => [
                        'parent' => [
                            'name' => 'Foo',
                            'second_child' => null,
                            'first_child' => [
                                'id' => 'http://localhost.localdomain/embedded/bar',
                            ],
                            'id' => 'http://localhost.localdomain/resource/foo',
                        ],
                        'id' => 'http://localhost.localdomain/embedded/bar',
                    ],
                    'id' => 'http://localhost.localdomain/resource/foo',
                ]
            ]
        ];
    }

    protected function createNestedEntity()
    {
        $object = new TestAsset\Entity('foo', 'Foo');
        $object->first_child  = new TestAsset\EmbeddedEntityWithBackReference('bar', $object);
        $entity = new Entity($object, 'foo');
        $idProperty = new Property('id');
        $idProperty->setRoute('hostname/resource', ['id' => 'foo']);
        $entity->getProperties()->add($idProperty);

        return $entity;
    }

    protected function createNestedMetadataMap($maxDepth = null)
    {
        return new MetadataMap([
            'ZFTest\JsonLD\Plugin\TestAsset\Entity' => [
                'hydrator'   => 'Zend\Hydrator\ObjectProperty',
                'route_name' => 'hostname/resource',
                'route_identifier_name' => 'id',
                'entity_identifier_name' => 'id',
                'max_depth' => $maxDepth,
            ],
            'ZFTest\JsonLD\Plugin\TestAsset\EmbeddedEntityWithBackReference' => [
                'hydrator' => 'Zend\Hydrator\ObjectProperty',
                'route'    => 'hostname/embedded',
                'route_identifier_name' => 'id',
                'entity_identifier_name' => 'id',
            ],
        ]);
    }

    public function testSubsequentRenderEntityCalls()
    {
        $entity = $this->createNestedEntity();
        $metadataMap1 = $this->createNestedMetadataMap(0);
        $metadataMap2 = $this->createNestedMetadataMap(1);

        $this->plugin->setMetadataMap($metadataMap1);
        $result1 = $this->plugin->renderEntity($entity);

        $this->plugin->setMetadataMap($metadataMap2);
        $result2 = $this->plugin->renderEntity($entity);

        $this->assertNotEquals($result1, $result2);
    }

    /**
     * @param $collection
     * @param $metadataMap
     * @param $expectedResult
     * @param $exception
     *
     * @dataProvider renderCollectionWithMaxDepthProvider
     */
    public function testRenderCollectionWithMaxDepth($collection, $metadataMap, $expectedResult, $exception = null)
    {
        $this->plugin->setMetadataMap($metadataMap);

        if ($exception) {
            $this->setExpectedException($exception['class'], $exception['message']);
        }

        if (is_callable($collection)) {
            $collection = $collection();
        }

        $jsonLDCollection = $this->plugin->createCollection($collection);
        $result = $this->plugin->renderCollection($jsonLDCollection);

        $this->assertEquals($expectedResult, $result);
    }

    public function renderCollectionWithMaxDepthProvider()
    {
        return [
            [
                function () {
                    $object1 = new TestAsset\Entity('foo', 'Foo');
                    $object1->first_child  = new TestAsset\EmbeddedEntityWithBackReference('bar', $object1);
                    $object2 = new TestAsset\Entity('bar', 'Bar');
                    $object3 = new TestAsset\Entity('baz', 'Baz');

                    $collection = new TestAsset\Collection([
                        $object1,
                        $object2,
                        $object3
                    ]);

                    return $collection;
                },
                $this->createNestedCollectionMetadataMap(),
                null,
                [
                    'class'   => 'ZF\JsonLD\Exception\CircularReferenceException',
                    'message' => 'Circular reference detected in \'ZFTest\JsonLD\Plugin\TestAsset\Entity\'',
                ]
            ],
            [
                function () {
                    $object1 = new TestAsset\Entity('foo', 'Foo');
                    $object1->first_child  = new TestAsset\EmbeddedEntityWithBackReference('bar', $object1);
                    $object2 = new TestAsset\Entity('bar', 'Bar');
                    $object3 = new TestAsset\Entity('baz', 'Baz');

                    $collection = new TestAsset\Collection([
                        $object1,
                        $object2,
                        $object3
                    ]);

                    return $collection;
                },
                $this->createNestedCollectionMetadataMap(1),
                [
                    'id' => 'http://localhost.localdomain/contacts',
                    'totalItems' => 3,
                    'collection' => [
                        [
                            'name'         => 'Foo',
                            'second_child' => null,
                            'first_child'  => [
                                'parent' => [
                                    'id' => 'http://localhost.localdomain/resource/foo',
                                ],
                                'id' => 'http://localhost.localdomain/embedded/bar',
                            ],
                            'id' => 'http://localhost.localdomain/resource/foo',
                        ],
                        [
                            'name'         => 'Bar',
                            'first_child'  => null,
                            'second_child' => null,
                            'id'          => 'http://localhost.localdomain/resource/bar',
                        ],
                        [
                            'name'         => 'Baz',
                            'first_child'  => null,
                            'second_child' => null,
                            'id'          => 'http://localhost.localdomain/resource/baz',
                        ],
                    ],
                    '@context' => 'http://www.w3.org/ns/hydra/context.jsonld',
                    '@type' => 'Collection'
                ],
            ],
            [
                function () {
                    $object1 = new TestAsset\Entity('foo', 'Foo');
                    $object2 = new TestAsset\Entity('bar', 'Bar');

                    $collection = new TestAsset\Collection([
                        $object1,
                        $object2,
                    ]);
                    $object1->first_child = $collection;

                    return $collection;
                },
                $this->createNestedCollectionMetadataMap(),
                null,
                [
                    'class'   => 'ZF\JsonLD\Exception\CircularReferenceException',
                    'message' => 'Circular reference detected in \'ZFTest\JsonLD\Plugin\TestAsset\Entity\'',
                ]
            ],
            [
                function () {
                    $object1 = new TestAsset\Entity('foo', 'Foo');
                    $object2 = new TestAsset\Entity('bar', 'Bar');

                    $collection = new TestAsset\Collection([
                        $object1,
                        $object2,
                    ]);
                    $object1->first_child = $collection;

                    return $collection;
                },
                $this->createNestedCollectionMetadataMap(1),
                [
                    'id' => 'http://localhost.localdomain/contacts',
                    'totalItems' => 2,
                    'collection' => [
                        [
                            'name'         => 'Foo',
                            'second_child' => null,
                            'first_child'  => [
                                [
                                    'id' => 'http://localhost.localdomain/resource/foo',
                                ],
                                [
                                    'id' => 'http://localhost.localdomain/resource/bar',
                                ]
                            ],
                            'id' => 'http://localhost.localdomain/resource/foo',
                        ],
                        [
                            'name'         => 'Bar',
                            'first_child'  => null,
                            'second_child' => null,
                            'id'          => 'http://localhost.localdomain/resource/bar',
                        ],
                    ],
                    '@context' => 'http://www.w3.org/ns/hydra/context.jsonld',
                    '@type' => 'Collection'
                ],
            ]
        ];
    }

    protected function createNestedCollectionMetadataMap($maxDepth = null)
    {
        return new MetadataMap([
            'ZFTest\JsonLD\Plugin\TestAsset\Collection' => [
                'is_collection'       => true,
                'collection_name'     => 'collection',
                'route_name'          => 'hostname/contacts',
                'entity_route_name'   => 'hostname/embedded',
                'max_depth'           => $maxDepth,
            ],
            'ZFTest\JsonLD\Plugin\TestAsset\Entity' => [
                'hydrator'   => 'Zend\Hydrator\ObjectProperty',
                'route_name' => 'hostname/resource',
                'route_identifier_name' => 'id',
                'entity_identifier_name' => 'id',
            ],
            'ZFTest\JsonLD\Plugin\TestAsset\EmbeddedEntityWithBackReference' => [
                'hydrator' => 'Zend\Hydrator\ObjectProperty',
                'route'    => 'hostname/embedded',
                'route_identifier_name' => 'id',
                'entity_identifier_name' => 'id',
            ],
        ]);
    }

    /**
     * @group 102
     */
    public function testRenderingEntityTwiceMustNotDuplicatePropertyProperties()
    {
        $property = new Property('resource');
        $property->setRoute('resource', ['id' => 'user']);

        $entity = new Entity(
            (object) [
                'id'   => 'user',
                'name' => 'matthew',
                'resource' => $property,
            ],
            'user'
        );

        $rendered1 = $this->plugin->renderEntity($entity);
        $rendered2 = $this->plugin->renderEntity($entity);
        $this->assertEquals($rendered1, $rendered2);
    }

    /**
     * @group 102
     */
    public function testRenderingEntityTwiceMustNotDuplicatePropertyCollectionProperties()
    {
        $property = new Property('resource');
        $property->setRoute('resource', ['id' => 'user']);
        $properties = new PropertyCollection();
        $properties->add($property);

        $entity = new Entity(
            (object) [
                'id'   => 'user',
                'name' => 'matthew',
                'resources' => $properties,
            ],
            'user'
        );

        $rendered1 = $this->plugin->renderEntity($entity);
        $rendered2 = $this->plugin->renderEntity($entity);
        $this->assertEquals($rendered1, $rendered2);
    }

    public function testCreateEntityFromMetadataWithoutForcedSelfProperties()
    {
        $object = new TestAsset\Entity('foo', 'Foo');
        $metadata = new MetadataMap([
            'ZFTest\JsonLD\Plugin\TestAsset\Entity' => [
                'hydrator'        => 'Zend\Hydrator\ObjectProperty',
                'route_name'      => 'hostname/resource',
                'properties'          => [],
                'force_full_uri_id'   => false,
            ],
        ]);

        $this->plugin->setMetadataMap($metadata);
        $entity = $this->plugin->createEntityFromMetadata(
            $object,
            $metadata->get('ZFTest\JsonLD\Plugin\TestAsset\Entity')
        );
        $properties = $entity->getProperties();
        $this->assertFalse($properties->has('id'));
    }

    public function testCreateEntityWithoutForcedSelfProperties()
    {
        $object = new TestAsset\Entity('foo', 'Foo');

        $metadata = new MetadataMap([
            'ZFTest\JsonLD\Plugin\TestAsset\Entity' => [
                'hydrator'        => 'Zend\Hydrator\ObjectProperty',
                'route_name'      => 'hostname/resource',
                'properties'           => [],
                'force_full_uri_id'   => false,
            ],
        ]);
        $this->plugin->setMetadataMap($metadata);
        $entity = $this->plugin->createEntity($object, 'hostname/resource', 'id');
        $properties = $entity->getProperties();
        $this->assertFalse($properties->has('id'));
        $this->assertFalse($properties->has('self'));
    }

    public function testCreateCollectionFromMetadataWithoutForcedSelfProperties()
    {
        $set = new TestAsset\Collection([
            (object) ['id' => 'foo', 'name' => 'foo'],
            (object) ['id' => 'bar', 'name' => 'bar'],
            (object) ['id' => 'baz', 'name' => 'baz'],
        ]);

        $metadata = new MetadataMap([
            'ZFTest\JsonLD\Plugin\TestAsset\Collection' => [
                'is_collection'     => true,
                'route_name'        => 'hostname/contacts',
                'entity_route_name' => 'hostname/embedded',
                'properties'             => [],
                'force_full_uri_id'     => false,
            ],
        ]);

        $this->plugin->setMetadataMap($metadata);

        $collection = $this->plugin->createCollectionFromMetadata(
            $set,
            $metadata->get('ZFTest\JsonLD\Plugin\TestAsset\Collection')
        );
        $properties = $collection->getProperties();
        $this->assertFalse($properties->has('id'));
    }

    public function testCreateCollectionWithoutForcedSelfProperties()
    {
        $collection = ['foo' => 'bar'];
        $metadata = new MetadataMap([
            'ZF\JsonLD\Collection' => [
                'is_collection'     => true,
                'route_name'        => 'hostname/contacts',
                'entity_route_name' => 'hostname/embedded',
                'properties'        => [],
                'force_full_uri_id' => false,
            ],
        ]);
        $this->plugin->setMetadataMap($metadata);

        $result = $this->plugin->createCollection($collection);
        $properties  = $result->getProperties();
        $this->assertFalse($properties->has('self'));
    }

    /**
     * This is a special use-case. See comment in Hal::extractCollection.
     */
    public function testExtractCollectionShouldAddSelfPropertyToEntityIfEntityIsArray()
    {
        $object = ['id' => 'Foo'];
        $collection = new Collection([$object]);
        $collection->setEntityRoute('hostname/resource');
        $method = new \ReflectionMethod($this->plugin, 'extractCollection');
        $method->setAccessible(true);
        $result = $method->invoke($this->plugin, $collection);
        $this->assertTrue(isset($result[0]['id']));
    }

    public function assertIsEntity($entity)
    {
        $this->assertInternalType('array', $entity);
    }

    public function assertEntityHasViewProperty($viewProperty, $entity)
    {
        $this->assertIsEntity($entity);
        $this->assertArrayHasKey(
            $viewProperty,
            $entity,
            sprintf('JsonLD does not contain property "%s"', $viewProperty)
        );
    }

    public function assertPropertyEquals($match, $viewProperty, $entity)
    {
        $this->assertEntityHasViewProperty($viewProperty, $entity);
        $property = $entity[$viewProperty];
        $this->assertArrayHasKey(
            $viewProperty,
            $entity,
            sprintf(
                '%s relational property does not have an href; received %s',
                $viewProperty,
                var_export($property, 1)
            )
        );
        $this->assertEquals($match, $property);
    }

    public function testRendersEntityWithAssociatedProperties()
    {
        $item = new Entity([
            'foo' => 'bar',
            'id'  => 'identifier',
        ], 'identifier');
        $properties = $item->getProperties();
        $self  = new Property('id');
        $self->setRoute('resource')->setRouteParams(['id' => 'identifier']);
        $properties->add($self);

        $result = $this->plugin->renderEntity($item);

        $this->assertPropertyEquals('http://localhost.localdomain/resource/identifier', 'id', $result);
        $this->assertArrayHasKey('foo', $result);
        $this->assertEquals('bar', $result['foo']);
    }

    public function testCanRenderStdclassEntity()
    {
        $item = (object) [
            'foo' => 'bar',
            'id'  => 'identifier',
        ];

        $item  = new Entity($item, 'identifier');
        $properties = $item->getProperties();
        $self  = new Property('id');
        $self->setRoute('resource')->setRouteParams(['id' => 'identifier']);
        $properties->add($self);

        $result = $this->plugin->renderEntity($item);

        $this->assertPropertyEquals('http://localhost.localdomain/resource/identifier', 'id', $result);
        $this->assertArrayHasKey('foo', $result);
        $this->assertEquals('bar', $result['foo']);
    }

    public function testCanSerializeHydratableEntity()
    {
        $this->plugin->addHydrator(
            'ZFTest\JsonLD\TestAsset\ArraySerializable',
            new Hydrator\ArraySerializable()
        );

        $item  = new JsonLDTestAsset\ArraySerializable();
        $item  = new Entity(new JsonLDTestAsset\ArraySerializable(), 'identifier');
        $properties = $item->getProperties();
        $self  = new Property('id');
        $self->setRoute('resource')->setRouteParams(['id' => 'identifier']);
        $properties->add($self);

        $result = $this->plugin->renderEntity($item);

        $this->assertPropertyEquals('http://localhost.localdomain/resource/identifier', 'id', $result);
        $this->assertArrayHasKey('foo', $result);
        $this->assertEquals('bar', $result['foo']);
    }

    public function testUsesDefaultHydratorIfAvailable()
    {
        $this->plugin->setDefaultHydrator(
            new Hydrator\ArraySerializable()
        );

        $item  = new JsonLDTestAsset\ArraySerializable();
        $item  = new Entity(new JsonLDTestAsset\ArraySerializable(), 'identifier');
        $properties = $item->getProperties();
        $self  = new Property('id');
        $self->setRoute('resource')->setRouteParams(['id' => 'identifier']);
        $properties->add($self);

        $result = $this->plugin->renderEntity($item);

        $this->assertPropertyEquals('http://localhost.localdomain/resource/identifier', 'id', $result);
        $this->assertArrayHasKey('foo', $result);
        $this->assertEquals('bar', $result['foo']);
    }

    public function testCanRenderNonPaginatedCollection()
    {
        $prototype = ['foo' => 'bar'];
        $items = [];
        foreach (range(1, 100) as $id) {
            $item       = $prototype;
            $item['id'] = $id;
            $items[]    = $item;
        }

        $collection = new Collection($items);
        $collection->setCollectionRoute('resource');
        $collection->setEntityRoute('resource');
        $properties = $collection->getProperties();
        $self  = new Property('id');
        $self->setRoute('resource');
        $properties->add($self);

        $result = $this->plugin->renderCollection($collection);

        $this->assertPropertyEquals('http://localhost.localdomain/resource', 'id', $result);

        $this->assertArrayHasKey('member', $result);
        $this->assertInternalType('array', $result['member']);
        $this->assertEquals(100, count($result['member']));

        foreach ($result['member'] as $key => $item) {
            $id = $key + 1;

            $this->assertPropertyEquals($id, 'id', $item);
            $this->assertArrayHasKey('id', $item, var_export($item, 1));
            $this->assertEquals($id, $item['id']);
            $this->assertArrayHasKey('foo', $item);
            $this->assertEquals('bar', $item['foo']);
        }
    }

    public function testCanRenderPaginatedCollection()
    {
        $prototype = ['foo' => 'bar'];
        $items = [];
        foreach (range(1, 100) as $id) {
            $item       = $prototype;
            $item['id'] = $id;
            $items[]    = $item;
        }
        $adapter   = new ArrayPaginator($items);
        $paginator = new Paginator($adapter);

        $collection = new Collection($paginator);
        $collection->setPageSize(5);
        $collection->setPage(3);
        $collection->setCollectionRoute('resource');
        $collection->setEntityRoute('resource');
        $properties  = $collection->getProperties();
        $idProperty = new Property('id');
        $idProperty->setRoute('resource');
        $properties->add($idProperty);

        $result = $this->plugin->renderCollection($collection);

        $this->assertInternalType('array', $result, var_export($result, 1));
        $this->assertPropertyEquals('http://localhost.localdomain/resource', 'id', $result);
        $this->assertPropertyEquals('http://localhost.localdomain/resource?page=3', 'id', $result['view']);
        $this->assertPropertyEquals('http://localhost.localdomain/resource', 'firstPage', $result['view']);
        $this->assertPropertyEquals('http://localhost.localdomain/resource?page=20', 'lastPage', $result['view']);
        $this->assertPropertyEquals('http://localhost.localdomain/resource?page=2', 'previousPage', $result['view']);
        $this->assertPropertyEquals('http://localhost.localdomain/resource?page=4', 'nextPage', $result['view']);

        $this->assertArrayHasKey('member', $result);
        $this->assertInternalType('array', $result['member']);
        $this->assertEquals(5, count($result['member']));

        foreach ($result['member'] as $key => $item) {
            $id = $key + 11;

            $this->assertPropertyEquals($id, 'id', $item);
            $this->assertArrayHasKey('id', $item, var_export($item, 1));
            $this->assertEquals($id, $item['id']);
            $this->assertArrayHasKey('foo', $item);
            $this->assertEquals('bar', $item['foo']);
        }
    }

    public function invalidPages()
    {
        return [
            '-1'   => [-1],
            '1000' => [1000],
        ];
    }

    /**
     * @dataProvider invalidPages
     */
    public function testRenderingPaginatedCollectionCanReturnApiProblemIfPageIsTooHighOrTooLow($page)
    {
        $prototype = ['foo' => 'bar'];
        $items = [];
        foreach (range(1, 100) as $id) {
            $item       = $prototype;
            $item['id'] = $id;
            $items[]    = $item;
        }
        $adapter   = new ArrayPaginator($items);
        $paginator = new Paginator($adapter);

        $collection = new Collection($paginator, 'resource');
        $collection->setPageSize(5);

        // Using reflection object so we can force a negative page number if desired
        $r = new ReflectionObject($collection);
        $p = $r->getProperty('page');
        $p->setAccessible(true);
        $p->setValue($collection, $page);

        /* @var \ZF\ApiProblem\ApiProblem*/
        $result = $this->plugin->renderCollection($collection);

        $this->assertInstanceOf('ZF\ApiProblem\ApiProblem', $result, var_export($result, 1));

        $data = $result->toArray();
        $this->assertArrayHasKey('status', $data, var_export($result, 1));
        $this->assertEquals(409, $data['status']);
        $this->assertArrayHasKey('detail', $data);
        $this->assertEquals('Invalid page provided', $data['detail']);
    }

    public function testRendersAttributesAsPartOfNonPaginatedCollection()
    {
        $attributes = [
            'count' => 100,
            'type'  => 'foo',
        ];

        $prototype = ['foo' => 'bar'];
        $items = [];
        foreach (range(1, 100) as $id) {
            $item       = $prototype;
            $item['id'] = $id;
            $items[]    = $item;
        }

        $collection = new Collection($items, 'resource');
        $collection->setAttributes($attributes);

        $result = $this->plugin->renderCollection($collection);

        $this->assertInternalType('array', $result, var_export($result, 1));
        $this->assertArrayHasKey('count', $result, var_export($result, 1));
        $this->assertEquals(100, $result['count']);
        $this->assertArrayHasKey('type', $result);
        $this->assertEquals('foo', $result['type']);
    }

    public function testRendersAttributeAsPartOfPaginatedCollection()
    {
        $attributes = [
            'count' => 100,
            'type'  => 'foo',
        ];

        $prototype = ['foo' => 'bar'];
        $items = [];
        foreach (range(1, 100) as $id) {
            $item       = $prototype;
            $item['id'] = $id;
            $items[]    = $item;
        }
        $adapter   = new ArrayPaginator($items);
        $paginator = new Paginator($adapter);

        $collection = new Collection($paginator);
        $collection->setPageSize(5);
        $collection->setPage(3);
        $collection->setAttributes($attributes);
        $collection->setCollectionRoute('resource');
        $collection->setEntityRoute('resource');
        $properties = $collection->getProperties();
        $self  = new Property('self');
        $self->setRoute('resource');
        $properties->add($self);

        $result = $this->plugin->renderCollection($collection);

        $this->assertInternalType('array', $result, var_export($result, 1));
        $this->assertArrayHasKey('count', $result, var_export($result, 1));
        $this->assertEquals(100, $result['count']);
        $this->assertArrayHasKey('type', $result);
        $this->assertEquals('foo', $result['type']);
    }

    public function testCanRenderNestedEntitiesAsEmbeddedEntities()
    {
        $this->router->addRoute('user', new Segment('/user[/:id]'));

        $child = new Entity([
            'id'     => 'matthew',
            'name'   => 'matthew',
            'github' => 'weierophinney',
        ], 'matthew');
        $property = new Property('id');
        $property->setRoute('user')->setRouteParams(['id' => 'matthew']);
        $child->getProperties()->add($property);

        $item = new Entity([
            'foo'  => 'bar',
            'id'   => 'identifier',
            'user' => $child,
        ], 'identifier');
        $property = new Property('id');
        $property->setRoute('resource')->setRouteParams(['id' => 'identifier']);
        $item->getProperties()->add($property);

        $result = $this->plugin->renderEntity($item);

        $this->assertInternalType('array', $result, var_export($result, 1));
        $this->assertArrayHasKey('user', $result);
        $user = $result['user'];
        $this->assertRelationalPropertyContains('/user/matthew', 'id', $user);

        foreach ($child->entity as $key => $value) {
            if ($key === 'id') {
                $this->assertArrayHasKey('id', $user);
                $this->assertContains($value, $user['id']);
                continue;
            }
            $this->assertArrayHasKey($key, $user);
            $this->assertEquals($value, $user[$key]);
        }
    }

    public function testRendersEmbeddedEntitiesOfIndividualNonPaginatedCollections()
    {
        $this->router->addRoute('user', new Segment('/user[/:id]'));

        $child = new Entity([
            'id'     => 'matthew',
            'name'   => 'matthew',
            'github' => 'weierophinney',
        ], 'matthew');
        $property = new Property('id');
        $property->setRoute('user')->setRouteParams(['id' => 'matthew']);
        $child->getProperties()->add($property);

        $prototype = ['foo' => 'bar', 'user' => $child];
        $items = [];
        foreach (range(1, 3) as $id) {
            $item       = $prototype;
            $item['id'] = $id;
            $items[]    = $item;
        }

        $collection = new Collection($items);
        $collection->setCollectionRoute('resource');
        $collection->setEntityRoute('resource');
        $properties = $collection->getProperties();
        $self  = new Property('id');
        $self->setRoute('resource');
        $properties->add($self);

        $result = $this->plugin->renderCollection($collection);

        $this->assertInternalType('array', $result, var_export($result, 1));

        $collection = $result['member'];
        foreach ($collection as $item) {
            $this->assertArrayHasKey('user', $item);
            $user = $item['user'];
            $this->assertRelationalPropertyContains('/user/matthew', 'id', $user);

            foreach ($child->entity as $key => $value) {
                if ($key === 'id') {
                    $this->assertArrayHasKey('id', $user);
                    $this->assertContains($value, $user['id']);
                    continue;
                }
                $this->assertArrayHasKey($key, $user);
                $this->assertEquals($value, $user[$key]);
            }
        }
    }

    public function testRendersEmbeddedEntitiesOfIndividualPaginatedCollections()
    {
        $this->router->addRoute('user', new Segment('/user[/:id]'));

        $child = new Entity([
            'id'     => 'matthew',
            'name'   => 'matthew',
            'github' => 'weierophinney',
        ], 'matthew');
        $property = new Property('id');
        $property->setRoute('user')->setRouteParams(['id' => 'matthew']);
        $child->getProperties()->add($property);

        $prototype = ['foo' => 'bar', 'user' => $child];
        $items = [];
        foreach (range(1, 3) as $id) {
            $item       = $prototype;
            $item['id'] = $id;
            $items[]    = $item;
        }
        $adapter   = new ArrayPaginator($items);
        $paginator = new Paginator($adapter);

        $collection = new Collection($paginator);
        $collection->setPageSize(5);
        $collection->setPage(1);
        $collection->setCollectionRoute('resource');
        $collection->setEntityRoute('resource');
        $properties  = $collection->getProperties();
        $idProperty = new Property('id');
        $idProperty->setRoute('resource');
        $properties->add($idProperty);

        $result = $this->plugin->renderCollection($collection);

        $this->assertInternalType('array', $result, var_export($result, 1));
        $collection = $result['member'];
        foreach ($collection as $item) {
            $this->assertArrayHasKey('user', $item, var_export($item, 1));

            $user = $item['user'];
            $this->assertRelationalPropertyContains('/user/matthew', 'id', $user);

            foreach ($child->entity as $key => $value) {
                if ($key === 'id') {
                    $this->assertArrayHasKey('id', $user);
                    $this->assertContains($value, $user['id']);
                    continue;
                }
                $this->assertArrayHasKey($key, $user);
                $this->assertEquals($value, $user[$key]);
            }
        }
    }

    public function testAllowsSpecifyingAlternateCallbackForReturningEntityId()
    {
        $this->plugin->getEventManager()->attach('getIdFromEntity', function ($e) {
            $entity = $e->getParam('entity');

            if (!is_array($entity)) {
                return false;
            }

            if (array_key_exists('name', $entity)) {
                return $entity['name'];
            }

            return false;
        }, 10);

        $prototype = ['foo' => 'bar'];
        $items = [];
        foreach (range(1, 100) as $id) {
            $item         = $prototype;
            $item['name'] = $id;
            $items[]      = $item;
        }

        $collection = new Collection($items);
        $collection->setCollectionRoute('resource');
        $collection->setEntityRoute('resource');
        $properties  = $collection->getProperties();
        $idProperty = new Property('id');
        $idProperty->setRoute('resource');
        $properties->add($idProperty);

        $result = $this->plugin->renderCollection($collection);

        $this->assertInternalType('array', $result, var_export($result, 1));
        $this->assertPropertyEquals('http://localhost.localdomain/resource', 'id', $result);

        $this->assertArrayHasKey('member', $result);
        $this->assertInternalType('array', $result['member']);
        $this->assertEquals(100, count($result['member']));

        foreach ($result['member'] as $key => $item) {
            $id = $key + 1;

            $this->assertPropertyEquals('http://localhost.localdomain/resource/' . $id, 'id', $item);
            $this->assertArrayHasKey('name', $item, var_export($item, 1));
            $this->assertEquals($id, $item['name']);
            $this->assertArrayHasKey('foo', $item);
            $this->assertEquals('bar', $item['foo']);
        }
    }

    /**
     * @group 100
     */
    public function testRenderEntityPostEventIsTriggered()
    {
        $entity = ['id' => 1, 'foo' => 'bar'];
        $jsonLDEntity = new Entity($entity, 1);

        $triggered = false;
        $this->plugin->getEventManager()->attach('renderEntity.post', function ($e) use (&$triggered) {
            $triggered = true;
        });

        $this->plugin->renderEntity($jsonLDEntity);
        $this->assertTrue($triggered);
    }
}
