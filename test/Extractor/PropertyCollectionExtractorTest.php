<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2015 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZFTest\JsonLD\Extractor;

use PHPUnit_Framework_TestCase as TestCase;
use ZF\JsonLD\Extractor\PropertyCollectionExtractor;
use ZF\JsonLD\Property\Property;
use ZF\JsonLD\Property\PropertyCollection;

class PropertyCollectionExtractorTest extends TestCase
{
    protected $propertyCollectionExtractor;

    public function setUp()
    {
        $propertyExtractor = $this->getMockBuilder('ZF\JsonLD\Extractor\PropertyExtractor')
            ->disableOriginalConstructor()
            ->getMock();

        $this->propertyCollectionExtractor = new PropertyCollectionExtractor($propertyExtractor);
    }

    public function testExtractGivenPropertyCollectionShouldReturnArrayWithExtractionOfEachProperty()
    {
        $propertyCollection = new PropertyCollection();
        $propertyCollection->add(Property::factory([
            'key' => 'foo',
            'url' => 'http://example.com/foo',
        ]));
        $propertyCollection->add(Property::factory([
            'key' => 'bar',
            'url' => 'http://example.com/bar',
        ]));
        $propertyCollection->add(Property::factory([
            'key' => 'baz',
            'url' => 'http://example.com/baz',
        ]));

        $result = $this->propertyCollectionExtractor->extract($propertyCollection);

        $this->assertInternalType('array', $result);
        $this->assertCount($propertyCollection->count(), $result);
    }

    public function testPropertyCollectionWithTwoPropertiesForSameRelationShouldReturnArrayWithOneKeyAggregatingProperties()
    {
        $propertyCollection = new PropertyCollection();
        $propertyCollection->add(Property::factory([
            'key' => 'foo',
            'url' => 'http://example.com/foo',
        ]));
        $propertyCollection->add(Property::factory([
            'key' => 'foo',
            'url' => 'http://example.com/bar',
        ]));
        $propertyCollection->add(Property::factory([
            'key' => 'baz',
            'url' => 'http://example.com/baz',
        ]));

        $result = $this->propertyCollectionExtractor->extract($propertyCollection);

        $this->assertInternalType('array', $result);
        $this->assertCount(2, $result);
        $this->assertInternalType('array', $result['foo']);
        $this->assertCount(2, $result['foo']);
    }
}
