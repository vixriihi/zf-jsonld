<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZF\JsonLD;

use Traversable;
use Zend\Stdlib\ArrayUtils;

/**
 * Model a collection for use with HAL payloads
 */
class Collection implements Property\PropertyCollectionAwareInterface
{
    /**
     * Additional attributes to render with the collection
     *
     * @var array
     */
    protected $attributes = [];

    /**
     * @var array|Traversable|\Zend\Paginator\Paginator
     */
    protected $collection;

    /**
     * Name of collection (used as property in the object)
     *
     * @var string
     */
    protected $collectionName = 'member';

    /**
     * @var string
     */
    protected $collectionRoute;

    /**
     * @var array
     */
    protected $collectionRouteOptions = [];

    /**
     * @var array
     */
    protected $collectionRouteParams = [];

    /**
     * Name of the field representing the identifier
     *
     * @var string
     */
    protected $entityIdentifierName = 'id';

    /**
     * Name of the route parameter identifier for individual entities of the collection
     *
     * @var string
     */
    protected $routeIdentifierName = 'id';

    /**
     * @var Property\PropertyCollection
     */
    protected $properties;

    /**
     * Current page
     *
     * @var int
     */
    protected $page = 1;

    /**
     * Number of entities per page
     *
     * @var int
     */
    protected $pageSize = 30;

    /**
     * @var Property\PropertyCollection
     */
    protected $entityProperties;

    /**
     * @var string
     */
    protected $entityRoute;

    /**
     * @var array
     */
    protected $entityRouteOptions = [];

    /**
     * @var array
     */
    protected $entityRouteParams = [];

    /**
     * @param  array|Traversable|\Zend\Paginator\Paginator $collection
     * @param  string $entityRoute
     * @param  array|Traversable $entityRouteParams
     * @param  array|Traversable $entityRouteOptions
     * @throws Exception\InvalidCollectionException
     */
    public function __construct($collection, $entityRoute = null, $entityRouteParams = null, $entityRouteOptions = null)
    {
        if (!is_array($collection) && !$collection instanceof Traversable) {
            throw new Exception\InvalidCollectionException(sprintf(
                '%s expects an array or Traversable; received "%s"',
                __METHOD__,
                (is_object($collection) ? get_class($collection) : gettype($collection))
            ));
        }

        $this->collection = $collection;

        if (null !== $entityRoute) {
            $this->setEntityRoute($entityRoute);
        }
        if (null !== $entityRouteParams) {
            $this->setEntityRouteParams($entityRouteParams);
        }
        if (null !== $entityRouteOptions) {
            $this->setEntityRouteOptions($entityRouteOptions);
        }
    }

    /**
     * Set additional attributes to render as part of the collection
     *
     * @param  array $attributes
     * @return self
     */
    public function setAttributes(array $attributes)
    {
        $this->attributes = $attributes;
        return $this;
    }

    /**
     * Set the collection name (for use within the member object)
     *
     * @param  string $name
     * @return self
     */
    public function setCollectionName($name)
    {
        $this->collectionName = (string) $name;
        return $this;
    }

    /**
     * Set the collection route; used for generating pagination properties
     *
     * @param  string $route
     * @return self
     */
    public function setCollectionRoute($route)
    {
        $this->collectionRoute = (string) $route;
        return $this;
    }

    /**
     * Set options to use with the collection route; used for generating pagination properties
     *
     * @param  array|Traversable $options
     * @return self
     * @throws Exception\InvalidArgumentException
     */
    public function setCollectionRouteOptions($options)
    {
        if ($options instanceof Traversable) {
            $options = ArrayUtils::iteratorToArray($options);
        }
        if (!is_array($options)) {
            throw new Exception\InvalidArgumentException(sprintf(
                '%s expects an array or Traversable; received "%s"',
                __METHOD__,
                (is_object($options) ? get_class($options) : gettype($options))
            ));
        }
        $this->collectionRouteOptions = $options;
        return $this;
    }

    /**
     * Set parameters/substitutions to use with the collection route; used for generating pagination properties
     *
     * @param  array|Traversable $params
     * @return self
     * @throws Exception\InvalidArgumentException
     */
    public function setCollectionRouteParams($params)
    {
        if ($params instanceof Traversable) {
            $params = ArrayUtils::iteratorToArray($params);
        }
        if (!is_array($params)) {
            throw new Exception\InvalidArgumentException(sprintf(
                '%s expects an array or Traversable; received "%s"',
                __METHOD__,
                (is_object($params) ? get_class($params) : gettype($params))
            ));
        }
        $this->collectionRouteParams = $params;
        return $this;
    }

    /**
     * Set the route identifier name
     *
     * @param  string $identifier
     * @return self
     */
    public function setRouteIdentifierName($identifier)
    {
        $this->routeIdentifierName = $identifier;
        return $this;
    }

    /**
     * Set the entity identifier name
     *
     * @param  string $identifier
     * @return self
     */
    public function setEntityIdentifierName($identifier)
    {
        $this->entityIdentifierName = $identifier;
        return $this;
    }

    /**
     * Set property collection
     *
     * @param  Property\PropertyCollection $properties
     * @return self
     */
    public function setProperties(Property\PropertyCollection $properties)
    {
        $this->properties = $properties;
        return $this;
    }

    /**
     * Set current page
     *
     * @param  int $page
     * @return self
     * @throws Exception\InvalidArgumentException for non-positive and/or non-integer values
     */
    public function setPage($page)
    {
        if (!is_int($page) && !is_numeric($page)) {
            throw new Exception\InvalidArgumentException(sprintf(
                'Page must be an integer; received "%s"',
                gettype($page)
            ));
        }

        $page = (int) $page;
        if ($page < 1) {
            throw new Exception\InvalidArgumentException(sprintf(
                'Page must be a positive integer; received "%s"',
                $page
            ));
        }

        $this->page = $page;
        return $this;
    }

    /**
     * Set page size
     *
     * @param  int $size
     * @return self
     * @throws Exception\InvalidArgumentException for non-positive and/or non-integer values
     */
    public function setPageSize($size)
    {
        if (!is_int($size) && !is_numeric($size)) {
            throw new Exception\InvalidArgumentException(sprintf(
                'Page size must be an integer; received "%s"',
                gettype($size)
            ));
        }

        $size = (int) $size;
        if ($size < 1 && $size !== -1) {
            throw new Exception\InvalidArgumentException(sprintf(
                'size must be a positive integer or -1 (to disable pagination); received "%s"',
                $size
            ));
        }

        $this->pageSize = $size;
        return $this;
    }

    /**
     * Set default set of properties to use for entities
     *
     * @param  Property\PropertyCollection $properties
     * @return self
     */
    public function setEntityProperties(Property\PropertyCollection $properties)
    {
        $this->entityProperties = $properties;
        return $this;
    }

    /**
     * Set the entity route
     *
     * @param  string $route
     * @return self
     */
    public function setEntityRoute($route)
    {
        $this->entityRoute = (string) $route;
        return $this;
    }

    /**
     * Set options to use with the entity route
     *
     * @param  array|Traversable $options
     * @return self
     * @throws Exception\InvalidArgumentException
     */
    public function setEntityRouteOptions($options)
    {
        if ($options instanceof Traversable) {
            $options = ArrayUtils::iteratorToArray($options);
        }
        if (!is_array($options)) {
            throw new Exception\InvalidArgumentException(sprintf(
                '%s expects an array or Traversable; received "%s"',
                __METHOD__,
                (is_object($options) ? get_class($options) : gettype($options))
            ));
        }
        $this->entityRouteOptions = $options;
        return $this;
    }

    /**
     * Set parameters/substitutions to use with the entity route
     *
     * @param  array|Traversable $params
     * @return self
     * @throws Exception\InvalidArgumentException
     */
    public function setEntityRouteParams($params)
    {
        if ($params instanceof Traversable) {
            $params = ArrayUtils::iteratorToArray($params);
        }
        if (!is_array($params)) {
            throw new Exception\InvalidArgumentException(sprintf(
                '%s expects an array or Traversable; received "%s"',
                __METHOD__,
                (is_object($params) ? get_class($params) : gettype($params))
            ));
        }
        $this->entityRouteParams = $params;
        return $this;
    }

    /**
     * Get property collection
     *
     * @return Property\PropertyCollection
     */
    public function getProperties()
    {
        if (!$this->properties instanceof Property\PropertyCollection) {
            $this->setProperties(new Property\PropertyCollection());
        }
        return $this->properties;
    }

    /**
     * Retrieve default entity properties, if any
     *
     * @return null|Property\PropertyCollection
     */
    public function getEntityProperties()
    {
        return $this->entityProperties;
    }

    /**
     * Attributes
     *
     * @return array
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * Collection
     *
     * @return array|Traversable|\Zend\Paginator\Paginator
     */
    public function getCollection()
    {
        return $this->collection;
    }

    /**
     * Collection Name
     *
     * @return string
     */
    public function getCollectionName()
    {
        return $this->collectionName;
    }

    /**
     * Collection Route
     *
     * @return string
     */
    public function getCollectionRoute()
    {
        return $this->collectionRoute;
    }

    /**
     * Collection Route Options
     *
     * @return string
     */
    public function getCollectionRouteOptions()
    {
        return $this->collectionRouteOptions;
    }

    /**
     * Collection Route Params
     *
     * @return string
     */
    public function getCollectionRouteParams()
    {
        return $this->collectionRouteParams;
    }

    /**
     * Route Identifier Name
     *
     * @return string
     */
    public function getRouteIdentifierName()
    {
        return $this->routeIdentifierName;
    }

    /**
     * Entity Identifier Name
     *
     * @return string
     */
    public function getEntityIdentifierName()
    {
        return $this->entityIdentifierName;
    }

    /**
     * Entity Route
     *
     * @return string
     */
    public function getEntityRoute()
    {
        return $this->entityRoute;
    }

    /**
     * Entity Route Options
     *
     * @return array
     */
    public function getEntityRouteOptions()
    {
        return $this->entityRouteOptions;
    }

    /**
     * Entity Route Params
     *
     * @return array
     */
    public function getEntityRouteParams()
    {
        return $this->entityRouteParams;
    }

    /**
     * Page
     *
     * @return int
     */
    public function getPage()
    {
        return $this->page;
    }

    /**
     * Page Size
     *
     * @return int
     */
    public function getPageSize()
    {
        return $this->pageSize;
    }
}
