<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZF\JsonLD\Property;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use ZF\ApiProblem\Exception;

/**
 * Object describing a collection of properties
 */
class PropertyCollection implements Countable, IteratorAggregate
{
    /**
     * @var array
     */
    protected $properties = [];

    /**
     * Return a count of properties
     *
     * @return int
     */
    public function count()
    {
        return count($this->properties);
    }

    /**
     * Retrieve internal iterator
     *
     * @return ArrayIterator
     */
    public function getIterator()
    {
        return new ArrayIterator($this->properties);
    }

    /**
     * Add a property
     *
     * @param  Property $property
     * @param  bool $overwrite
     * @return self
     * @throws Exception\DomainException
     */
    public function add(Property $property, $overwrite = false)
    {
        $keyword = $property->getKeyword();
        if (!isset($this->properties[$keyword]) || $overwrite || 'id' == $keyword) {
            $this->properties[$keyword] = $property;
            return $this;
        }

        if ($this->properties[$keyword] instanceof Property
            || $this->properties[$keyword] instanceof PropertyCollection) {
            $this->properties[$keyword] = [$this->properties[$keyword]];
        }

        if (!is_array($this->properties[$keyword])) {
            $type = (is_object($this->properties[$keyword])
                ? get_class($this->properties[$keyword])
                : gettype($this->properties[$keyword]));

            throw new Exception\DomainException(sprintf(
                '%s::$properties should be either a %s\Property or an array; however, it is a "%s"',
                __CLASS__,
                __NAMESPACE__,
                $type
            ));
        }

        $this->properties[$keyword][] = $property;
        return $this;
    }

    /**
     * Retrieve a property relation
     *
     * @param  string $keyword
     * @return Property|array|null
     */
    public function get($keyword)
    {
        if (!$this->has($keyword)) {
            return null;
        }
        return $this->properties[$keyword];
    }

    /**
     * Does a given property relation exist?
     *
     * @param  string $keyword
     * @return bool
     */
    public function has($keyword)
    {
        return array_key_exists($keyword, $this->properties);
    }

    /**
     * Remove a given property relation
     *
     * @param  string $keyword
     * @return bool
     */
    public function remove($keyword)
    {
        if (!$this->has($keyword)) {
            return false;
        }
        unset($this->properties[$keyword]);
        return true;
    }
}
