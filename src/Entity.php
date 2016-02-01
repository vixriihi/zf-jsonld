<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZF\JsonLD;

class Entity implements Property\PropertyCollectionAwareInterface
{
    protected $id;

    /**
     * @var Property\PropertyCollection
     */
    protected $properties;

    protected $entity;

    /**
     * @param  object|array $entity
     * @param  mixed $id
     * @throws Exception\InvalidEntityException if entity is not an object or array
     */
    public function __construct($entity, $id = null)
    {
        if (!is_object($entity) && !is_array($entity)) {
            throw new Exception\InvalidEntityException();
        }

        $this->entity      = $entity;
        $this->id          = $id;
    }

    /**
     * Retrieve properties
     *
     * @param  string $name
     * @throws Exception\InvalidArgumentException
     * @return mixed
     */
    public function &__get($name)
    {
        $names = [
            'entity' => 'entity',
            'id'     => 'id',
            '@id'    => 'id',
        ];
        $name = strtolower($name);
        if (!in_array($name, array_keys($names))) {
            throw new Exception\InvalidArgumentException(sprintf(
                'Invalid property name "%s"',
                $name
            ));
        }
        $prop = $names[$name];
        return $this->{$prop};
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
}
