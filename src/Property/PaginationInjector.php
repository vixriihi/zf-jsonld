<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZF\JsonLD\Property;

use Zend\Paginator\Paginator;
use Zend\Stdlib\ArrayUtils;
use ZF\ApiProblem\ApiProblem;
use ZF\JsonLD\Collection;

class PaginationInjector implements PaginationInjectorInterface
{
    /**
     * @inheritDoc
     */
    public function injectPaginationProperties(Collection $jsonLDCollection)
    {
        $collection = $jsonLDCollection->getCollection();
        if (! $collection instanceof Paginator) {
            return false;
        }

        $this->configureCollection($jsonLDCollection);

        $pageCount = count($collection);
        if ($pageCount === 0) {
            return true;
        }

        $page = $jsonLDCollection->getPage();

        if ($page < 1 || $page > $pageCount) {
            return new ApiProblem(409, 'Invalid page provided');
        }

        $this->injectProperties($jsonLDCollection);

        return true;
    }

    private function configureCollection(Collection $jsonLDCollection)
    {
        $collection = $jsonLDCollection->getCollection();
        $page       = $jsonLDCollection->getPage();
        $pageSize   = $jsonLDCollection->getPageSize();

        $collection->setItemCountPerPage($pageSize);
        $collection->setCurrentPageNumber($page);
    }

    private function injectProperties(Collection $jsonLDCollection)
    {
        $pageCollection = new PropertyCollection();
        $paginator = new Property('view');
        $this->injectIDPropertyNoPage($jsonLDCollection);
        $this->injectIDProperty($jsonLDCollection, $pageCollection);
        $this->injectFirstPageProperty($jsonLDCollection, $pageCollection);
        $this->injectLastPageProperty($jsonLDCollection, $pageCollection);
        $this->injectPrevPageProperty($jsonLDCollection, $pageCollection);
        $this->injectNextPageProperty($jsonLDCollection, $pageCollection);
        $paginator->setValue($pageCollection);
        $jsonLDCollection->getProperties()->add($paginator);
    }

    private function injectIDPropertyNoPage(Collection $jsonLDCollection)
    {
        $property = $this->createPaginationProperty('@id', $jsonLDCollection);
        $jsonLDCollection->getProperties()->add($property, true);
    }

    private function injectIDProperty(Collection $jsonLDCollection, PropertyCollection $pageCollection)
    {
        $page = $jsonLDCollection->getPage();
        $property = $this->createPaginationProperty('@id', $jsonLDCollection, $page);
        $pageCollection->add($property, true);
    }

    private function injectFirstPageProperty(Collection $jsonLDCollection, PropertyCollection $pageCollection)
    {
        $property = $this->createPaginationProperty('firstPage', $jsonLDCollection);
        $pageCollection->add($property);
    }

    private function injectLastPageProperty(Collection $jsonLDCollection, PropertyCollection $pageCollection)
    {
        $page = $jsonLDCollection->getCollection()->count();
        $property = $this->createPaginationProperty('lastPage', $jsonLDCollection, $page);
        $pageCollection->add($property);
    }

    private function injectPrevPageProperty(Collection $jsonLDCollection, PropertyCollection $pageCollection)
    {
        $page = $jsonLDCollection->getPage();
        $prev = ($page > 1) ? $page - 1 : false;

        if ($prev) {
            $property = $this->createPaginationProperty('previousPage', $jsonLDCollection, $prev);
            $pageCollection->add($property);
        }
    }

    private function injectNextPageProperty(Collection $jsonLDCollection, PropertyCollection $pageCollection)
    {
        $page      = $jsonLDCollection->getPage();
        $pageCount = $jsonLDCollection->getCollection()->count();
        $next      = ($page < $pageCount) ? $page + 1 : false;

        if ($next) {
            $property = $this->createPaginationProperty('nextPage', $jsonLDCollection, $next);
            $pageCollection->add($property);
        }
    }

    private function createPaginationProperty($property, Collection $jsonLDCollection, $page = null)
    {
        $options = ArrayUtils::merge(
            $jsonLDCollection->getCollectionRouteOptions(),
            ['query' => ['page' => $page]]
        );

        return Property::factory([
            'key'   => $property,
            'route' => [
                'name'    => $jsonLDCollection->getCollectionRoute(),
                'params'  => $jsonLDCollection->getCollectionRouteParams(),
                'options' => $options,
            ],
        ]);
    }
}
