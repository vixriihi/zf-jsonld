<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZFTest\JsonLD;

use ZF\JsonLD\Collection;
use ZF\JsonLD\Property\Property;
use ZF\JsonLD\Property\PropertyCollection;
use PHPUnit_Framework_TestCase as TestCase;
use stdClass;

class CollectionTest extends TestCase
{
    public function invalidCollections()
    {
        return [
            'null'       => [null],
            'true'       => [true],
            'false'      => [false],
            'zero-int'   => [0],
            'int'        => [1],
            'zero-float' => [0.0],
            'float'      => [1.1],
            'string'     => ['string'],
            'stdclass'   => [new stdClass],
        ];
    }

    /**
     * @dataProvider invalidCollections
     */
    public function testConstructorRaisesExceptionForNonTraversableCollection($collection)
    {
        $this->setExpectedException('ZF\JsonLD\Exception\InvalidCollectionException');
        $jsonLD = new Collection($collection, 'collection/route', 'item/route');
    }

    public function testPropertiesAreAccessibleFollowingConstruction()
    {
        $jsonLD = new Collection([], 'item/route', ['version' => 1], ['query' => 'format=json']);
        $this->assertEquals([], $jsonLD->getCollection());
        $this->assertEquals('item/route', $jsonLD->getEntityRoute());
        $this->assertEquals(['version' => 1], $jsonLD->getEntityRouteParams());
        $this->assertEquals(['query' => 'format=json'], $jsonLD->getEntityRouteOptions());
    }

    public function testDefaultPageIsOne()
    {
        $jsonLD = new Collection([], 'item/route');
        $this->assertEquals(1, $jsonLD->getPage());
    }

    public function testPageIsMutable()
    {
        $jsonLD = new Collection([], 'item/route');
        $jsonLD->setPage(5);
        $this->assertEquals(5, $jsonLD->getPage());
    }

    public function testDefaultPageSizeIsThirty()
    {
        $jsonLD = new Collection([], 'item/route');
        $this->assertEquals(30, $jsonLD->getPageSize());
    }

    public function testPageSizeIsMutable()
    {
        $jsonLD = new Collection([], 'item/route');
        $jsonLD->setPageSize(3);
        $this->assertEquals(3, $jsonLD->getPageSize());
    }

    public function testPageSizeAllowsNegativeOneAsValue()
    {
        $jsonLD = new Collection([], 'item/route');
        $jsonLD->setPageSize(-1);
        $this->assertEquals(-1, $jsonLD->getPageSize());
    }

    public function testDefaultCollectionNameIsItems()
    {
        $jsonLD = new Collection([], 'item/route');
        $this->assertEquals('member', $jsonLD->getCollectionName());
    }

    public function testCollectionNameIsMutable()
    {
        $jsonLD = new Collection([], 'item/route');
        $jsonLD->setCollectionName('records');
        $this->assertEquals('records', $jsonLD->getCollectionName());
    }

    public function testDefaultAttributesAreEmpty()
    {
        $jsonLD = new Collection([], 'item/route');
        $this->assertEquals([], $jsonLD->getAttributes());
    }

    public function testAttributesAreMutable()
    {
        $jsonLD = new Collection([], 'item/route');
        $attributes = [
            'count' => 1376,
            'order' => 'desc',
        ];
        $jsonLD->setAttributes($attributes);
        $this->assertEquals($attributes, $jsonLD->getAttributes());
    }

    public function testComposesPropertyCollectionByDefault()
    {
        $jsonLD = new Collection([], 'item/route');
        $this->assertInstanceOf('ZF\JsonLD\Property\PropertyCollection', $jsonLD->getProperties());
    }

    public function testPropertyCollectionMayBeInjected()
    {
        $jsonLD   = new Collection([], 'item/route');
        $properties = new PropertyCollection();
        $jsonLD->setProperties($properties);
        $this->assertSame($properties, $jsonLD->getProperties());
    }

    public function testAllowsSettingAdditionalEntityProperties()
    {
        $properties = new PropertyCollection();
        $properties->add(new Property('describedby'));
        $properties->add(new Property('orders'));
        $jsonLD   = new Collection([], 'item/route');
        $jsonLD->setEntityProperties($properties);
        $this->assertSame($properties, $jsonLD->getEntityProperties());
    }
}
