<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZF\JsonLD\Property;

use Traversable;
use Zend\Stdlib\ArrayUtils;
use Zend\Uri\Exception as UriException;
use Zend\Uri\UriFactory;
use ZF\ApiProblem\Exception\DomainException;
use ZF\JsonLD\Exception;

/**
 * Object describing a property
 */
class Property
{
    /**
     * @var string
     */
    protected $keyword;

    /**
     * @var string
     */
    protected $route;

    /**
     * @var array
     */
    protected $routeOptions = [];

    /**
     * @var array
     */
    protected $routeParams = [];

    /**
     * @var string
     */
    protected $url;

    /**
     * @var string|mixed
     */
    protected $value;

    /**
     * Create a property relation
     *
     * @todo  filtering and/or validation of relation string
     * @param string $keyword
     */
    public function __construct($keyword)
    {
        $this->keyword = (string) $keyword;
    }

    /**
     * Factory for creating properties
     *
     * @param  array $spec
     * @return self
     * @throws Exception\InvalidArgumentException if missing a "key" or invalid route specifications
     */
    public static function factory(array $spec)
    {
        if (!isset($spec['key'])) {
            throw new Exception\InvalidArgumentException(sprintf(
                '%s requires that the specification array contain a "key" element; none found',
                __METHOD__
            ));
        }
        $property = new static($spec['key']);

        if (isset($spec['value'])) {
            $property->setValue($spec['value']);
            return $property;
        }

        if (isset($spec['url'])) {
            $property->setUrl($spec['url']);
            return $property;
        }

        if (isset($spec['route'])) {
            $routeInfo = $spec['route'];
            if (is_string($routeInfo)) {
                $property->setRoute($routeInfo);
                return $property;
            }

            if (!is_array($routeInfo)) {
                throw new Exception\InvalidArgumentException(sprintf(
                    '%s requires that the specification array\'s "route" element be a string or array; received "%s"',
                    __METHOD__,
                    (is_object($routeInfo) ? get_class($routeInfo) : gettype($routeInfo))
                ));
            }

            if (!isset($routeInfo['name'])) {
                throw new Exception\InvalidArgumentException(sprintf(
                    '%s requires that the specification array\'s "route" array contain a "name" element; none found',
                    __METHOD__
                ));
            }
            $name    = $routeInfo['name'];
            $params  = isset($routeInfo['params']) && is_array($routeInfo['params'])
                ? $routeInfo['params']
                : [];
            $options = isset($routeInfo['options']) && is_array($routeInfo['options'])
                ? $routeInfo['options']
                : [];
            $property->setRoute($name, $params, $options);
            return $property;
        }

        return $property;
    }


    /**
     * Sets the value of the property
     *
     * @param array|mixed $value
     */
    public function setValue($value)
    {
        if ($this->hasUrl() || $this->hasRoute()) {
            throw new DomainException(sprintf(
                '%s already has a URL or route set; cannot set value',
                __CLASS__
            ));
        }
        if (!($value instanceof PropertyCollection || is_array($value) || is_scalar($value))) {
            throw new Exception\InvalidArgumentException(sprintf(
                '%s expects an array or scalar; received "%s"',
                __METHOD__,
                (is_object($value) ? get_class($value) : gettype($value))
            ));
        }
        $this->value = $value;
    }

    /**
     * Set the route to use when generating the relation URI
     *
     * If any params or options are passed, those will be passed to route assembly.
     *
     * @param  string $route
     * @param  null|array|Traversable $params
     * @param  null|array|Traversable $options
     * @return self
     * @throws DomainException
     */
    public function setRoute($route, $params = null, $options = null)
    {
        if ($this->hasUrl() || $this->hasValue()) {
            throw new DomainException(sprintf(
                '%s already has a URL or value set; cannot set route',
                __CLASS__
            ));
        }

        $this->route = (string) $route;
        if ($params) {
            $this->setRouteParams($params);
        }
        if ($options) {
            $this->setRouteOptions($options);
        }
        return $this;
    }

    /**
     * Set route assembly options
     *
     * @param  array|Traversable $options
     * @return self
     * @throws Exception\InvalidArgumentException
     */
    public function setRouteOptions($options)
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

        $this->routeOptions = $options;
        return $this;
    }

    /**
     * Set route assembly parameters/substitutions
     *
     * @param  array|Traversable $params
     * @return self
     * @throws Exception\InvalidArgumentException
     */
    public function setRouteParams($params)
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

        $this->routeParams = $params;
        return $this;
    }

    /**
     * Set an explicit URL for the property relation
     *
     * @param  string $url
     * @return self
     * @throws DomainException
     * @throws Exception\InvalidArgumentException
     */
    public function setUrl($url)
    {
        if ($this->hasRoute() || $this->hasValue()) {
            throw new DomainException(sprintf(
                '%s already has a route or value set; cannot set URL',
                __CLASS__
            ));
        }

        try {
            $uri = UriFactory::factory($url);
        } catch (UriException\ExceptionInterface $e) {
            throw new Exception\InvalidArgumentException(sprintf(
                'Received invalid URL: %s',
                $e->getMessage()
            ), $e->getCode(), $e);
        }

        if (!$uri->isValid()) {
            throw new Exception\InvalidArgumentException(
                'Received invalid URL'
            );
        }

        $this->url = $url;
        return $this;
    }

    /**
     * Retrieve the property relation
     *
     * @return string
     */
    public function getKeyword()
    {
        return $this->keyword;
    }

    /**
     * Return the route to be used to generate the property URL, if any
     *
     * @return null|string
     */
    public function getRoute()
    {
        return $this->route;
    }

    /**
     * Retrieve route assembly options, if any
     *
     * @return array
     */
    public function getRouteOptions()
    {
        return $this->routeOptions;
    }

    /**
     * Retrieve route assembly parameters/substitutions, if any
     *
     * @return array
     */
    public function getRouteParams()
    {
        return $this->routeParams;
    }

    /**
     * Retrieve the property URL, if set
     *
     * @return null|string
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * @return array|string
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * Is the property relation complete -- do we have either a URL or a route set?
     *
     * @return bool
     */
    public function isComplete()
    {
        return (!empty($this->url) || !empty($this->route) || !empty($this->value));
    }

    /**
     * Does the property have a route set?
     *
     * @return bool
     */
    public function hasRoute()
    {
        return !empty($this->route);
    }

    /**
     * Does the property have a URL set?
     *
     * @return bool
     */
    public function hasUrl()
    {
        return !empty($this->url);
    }

    /**
     * Does the property have value set
     *
     * @return bool
     */
    public function hasValue()
    {
        return !empty($this->value);
    }
}
