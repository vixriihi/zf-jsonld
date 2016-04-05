<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZFTest\JsonLD\Property;

use Zend\Paginator\Adapter\ArrayAdapter;
use Zend\Paginator\Paginator;
use ZF\JsonLD\Collection;
use ZF\JsonLD\Property\PaginationInjector;
use PHPUnit_Framework_TestCase as TestCase;

class PaginationInjectorTest extends TestCase
{
    /**
     * @param  int $pages
     * @param  int $currentPage
     * @return Collection
     */
    private function getHalCollection($pages, $currentPage)
    {
        $items = [];
        for ($i = 0; $i < $pages; $i++) {
            $items[] = [];
        }

        $adapter       = new ArrayAdapter($items);
        $collection    = new Paginator($adapter);
        $jsonLDCollection = new Collection($collection);

        $jsonLDCollection->setCollectionRoute('foo');
        $jsonLDCollection->setPage($currentPage);
        $jsonLDCollection->setPageSize(1);

        return $jsonLDCollection;
    }

    public function testInjectPaginationPropertiesGivenIntermediatePageShouldInjectAllProperties()
    {
        $jsonLDCollection = $this->getHalCollection(5, 2);

        $injector = new PaginationInjector();
        $injector->injectPaginationProperties($jsonLDCollection);

        $properties = $jsonLDCollection->getProperties();
        $links      = $properties->get('view')->getValue();
        $this->assertTrue($properties->has('id'));
        $this->assertTrue($links->has('id'));
        $this->assertTrue($links->has('firstPage'));
        $this->assertTrue($links->has('lastPage'));
        $this->assertTrue($links->has('previousPage'));
        $this->assertTrue($links->has('nextPage'));
    }

    public function testInjectPaginationPropertiesGivenFirstPageShouldInjectPropertiesExceptForPrevious()
    {
        $jsonLDCollection = $this->getHalCollection(5, 1);

        $injector = new PaginationInjector();
        $injector->injectPaginationProperties($jsonLDCollection);

        $properties = $jsonLDCollection->getProperties();
        $links      = $properties->get('view')->getValue();
        $this->assertTrue($properties->has('id'));
        $this->assertTrue($properties->has('id'));
        $this->assertTrue($links->has('firstPage'));
        $this->assertTrue($links->has('lastPage'));
        $this->assertFalse($links->has('previousPage'));
        $this->assertTrue($links->has('nextPage'));
    }

    public function testInjectPaginationPropertiesGivenLastPageShouldInjectPropertiesExceptForNext()
    {
        $jsonLDCollection = $this->getHalCollection(5, 5);

        $injector = new PaginationInjector();
        $injector->injectPaginationProperties($jsonLDCollection);

        $properties = $jsonLDCollection->getProperties();
        $links      = $properties->get('view')->getValue();
        $this->assertTrue($properties->has('id'));
        $this->assertTrue($links->has('id'));
        $this->assertTrue($links->has('firstPage'));
        $this->assertTrue($links->has('lastPage'));
        $this->assertTrue($links->has('previousPage'));
        $this->assertFalse($links->has('nextPage'));
    }

    public function testInjectPaginationPropertiesGivenEmptyCollectionShouldNotInjectAnyProperty()
    {
        $jsonLDCollection = $this->getHalCollection(0, 1);

        $injector = new PaginationInjector();
        $injector->injectPaginationProperties($jsonLDCollection);

        $properties = $jsonLDCollection->getProperties();
        $this->assertFalse($properties->has('id'));
        $this->assertFalse($properties->has('view'));
    }

    public function testInjectPaginationPropertiesGivenPageGreaterThanPageCountShouldReturnApiProblem()
    {
        $jsonLDCollection = $this->getHalCollection(5, 6);

        $injector = new PaginationInjector();
        $result = $injector->injectPaginationProperties($jsonLDCollection);

        $this->assertInstanceOf('ZF\ApiProblem\ApiProblem', $result);
        $this->assertEquals(409, $result->status);
    }

    public function testInjectPaginationPropertiesGivenCollectionRouteNameShouldInjectPropertiesWithSameRoute()
    {
        $jsonLDCollection = $this->getHalCollection(5, 2);

        $injector = new PaginationInjector();
        $injector->injectPaginationProperties($jsonLDCollection);

        $collectionRoute = $jsonLDCollection->getCollectionRoute();

        $properties = $jsonLDCollection->getProperties();
        $links      = $properties->get('view')->getValue();
        $this->assertEquals($collectionRoute, $properties->get('id')->getRoute());
        $this->assertEquals($collectionRoute, $links->get('id')->getRoute());
        $this->assertEquals($collectionRoute, $links->get('firstPage')->getRoute());
        $this->assertEquals($collectionRoute, $links->get('lastPage')->getRoute());
        $this->assertEquals($collectionRoute, $links->get('previousPage')->getRoute());
        $this->assertEquals($collectionRoute, $links->get('nextPage')->getRoute());
    }
}
