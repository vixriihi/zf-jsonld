<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZFTest\JsonLD;

use ZF\JsonLD\Entity;
use ZF\JsonLD\Property\PropertyCollection;
use PHPUnit_Framework_TestCase as TestCase;
use stdClass;

class EntityTest extends TestCase
{
    public function invalidEntities()
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
        ];
    }

    /**
     * @dataProvider invalidEntities
     */
    public function testConstructorRaisesExceptionForNonObjectNonArrayEntity($entity)
    {
        $this->setExpectedException('ZF\JsonLD\Exception\InvalidEntityException');
        $jsonLD = new Entity($entity, 'id');
    }

    public function testPropertiesAreAccessibleAfterConstruction()
    {
        $entity = new stdClass;
        $jsonLD    = new Entity($entity, 'id');
        $this->assertSame($entity, $jsonLD->entity);
        $this->assertEquals('id', $jsonLD->id);
    }

    public function testComposesPropertyCollectionByDefault()
    {
        $entity = new stdClass;
        $jsonLD    = new Entity($entity, 'id', 'route', ['foo' => 'bar']);
        $this->assertInstanceOf('ZF\JsonLD\Property\PropertyCollection', $jsonLD->getProperties());
    }

    public function testPropertyCollectionMayBeInjected()
    {
        $entity = new stdClass;
        $jsonLD    = new Entity($entity, 'id', 'route', ['foo' => 'bar']);
        $properties  = new PropertyCollection();
        $jsonLD->setProperties($properties);
        $this->assertSame($properties, $jsonLD->getProperties());
    }

    public function testRetrievingEntityCanReturnByReference()
    {
        $entity = ['foo' => 'bar'];
        $jsonLD    = new Entity($entity, 'id');
        $this->assertEquals($entity, $jsonLD->entity);

        $entity =& $jsonLD->entity;
        $entity['foo'] = 'baz';

        $secondRetrieval =& $jsonLD->entity;
        $this->assertEquals('baz', $secondRetrieval['foo']);
    }

    /**
     * @group 39
     */
    public function testConstructorAllowsNullIdentifier()
    {
        $jsonLD = new Entity(['foo' => 'bar'], null);
        $this->assertNull($jsonLD->id);
    }
}
