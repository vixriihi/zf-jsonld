<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZFTest\JsonLD\Property;

use ZF\JsonLD\Property\Property;
use ZF\JsonLD\Property\PropertyCollection;
use PHPUnit_Framework_TestCase as TestCase;

class PropertyCollectionTest extends TestCase
{
    public function setUp()
    {
        $this->properties = new PropertyCollection();
    }

    public function testCanAddDiscretePropertyRelations()
    {
        $describedby = new Property('describedby');
        $idLink = new Property('@id');
        $this->properties->add($describedby);
        $this->properties->add($idLink);

        $this->assertTrue($this->properties->has('describedby'));
        $this->assertSame($describedby, $this->properties->get('describedby'));
        $this->assertTrue($this->properties->has('@id'));
        $this->assertSame($idLink, $this->properties->get('@id'));
    }

    public function testCanAddDuplicatePropertyRelations()
    {
        $order1 = new Property('order');
        $order2 = new Property('order');

        $this->properties->add($order1)
                    ->add($order2);

        $this->assertTrue($this->properties->has('order'));
        $orders = $this->properties->get('order');
        $this->assertInternalType('array', $orders);
        $this->assertContains($order1, $orders);
        $this->assertContains($order2, $orders);
    }

    public function testCanRemovePropertyRelations()
    {
        $describedby = new Property('describedby');
        $this->properties->add($describedby);
        $this->assertTrue($this->properties->has('describedby'));
        $this->properties->remove('describedby');
        $this->assertFalse($this->properties->has('describedby'));
    }

    public function testCanOverwritePropertyRelations()
    {
        $order1 = new Property('order');
        $order2 = new Property('order');

        $this->properties
            ->add($order1)
            ->add($order2, true);

        $this->assertTrue($this->properties->has('order'));
        $orders = $this->properties->get('order');
        $this->assertSame($order2, $orders);
    }

    public function testCanIterateProperties()
    {
        $describedby = new Property('describedby');
        $idLink = new Property('@id');
        $this->properties->add($describedby);
        $this->properties->add($idLink);

        $this->assertEquals(2, $this->properties->count());
        $i = 0;
        foreach ($this->properties as $property) {
            $this->assertInstanceOf('ZF\JsonLD\Property\Property', $property);
            $i += 1;
        }
        $this->assertEquals(2, $i);
    }

    public function testCannotDuplicateSelf()
    {
        $first = new Property('@id');
        $second = new Property('@id');

        $this->properties
            ->add($first)
            ->add($second);

        $this->assertTrue($this->properties->has('@id'));
        $this->assertInstanceOf('ZF\JsonLD\Property\Property', $this->properties->get('@id'));
        $this->assertSame($second, $this->properties->get('@id'));
    }
}
