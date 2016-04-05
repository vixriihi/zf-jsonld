<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZF\JsonLD;

use Closure;
use ZF\JsonLD\Collection;
use ZF\JsonLD\Entity;
use ZF\JsonLD\Extractor\EntityExtractor;
use ZF\JsonLD\Exception;
use ZF\JsonLD\Property\Property;
use ZF\JsonLD\Property\PropertyCollection;
use ZF\JsonLD\Metadata\Metadata;

class ResourceFactory
{
    /**
     * @var EntityHydratorManager
     */
    protected $entityHydratorManager;

    /**
     * @var EntityExtractor
     */
    protected $entityExtractor;

    /**
     * @param EntityHydratorManager $entityHydratorManager
     * @param EntityExtractor $entityExtractor
     */
    public function __construct(EntityHydratorManager $entityHydratorManager, EntityExtractor $entityExtractor)
    {
        $this->entityHydratorManager = $entityHydratorManager;
        $this->entityExtractor       = $entityExtractor;
    }

    /**
     * Create a entity and/or collection based on a metadata map
     *
     * @param  object $object
     * @param  Metadata $metadata
     * @param  bool $renderEmbeddedEntities
     * @return Entity|Collection
     * @throws Exception\RuntimeException
     */
    public function createEntityFromMetadata($object, Metadata $metadata, $renderEmbeddedEntities = true)
    {
        if ($metadata->isCollection()) {
            return $this->createCollectionFromMetadata($object, $metadata);
        }

        $data = $this->entityExtractor->extract($object);

        $entityIdentifierName = $metadata->getEntityIdentifierName();
        if ($entityIdentifierName && ! isset($data[$entityIdentifierName])) {
            throw new Exception\RuntimeException(sprintf(
                'Unable to determine entity identifier for object of type "%s"; no fields matching "%s"',
                get_class($object),
                $entityIdentifierName
            ));
        }

        $id = ($entityIdentifierName) ? $data[$entityIdentifierName]: null;

        if (! $renderEmbeddedEntities) {
            $object = [];
        }

        $jsonLDEntity = new Entity($object, $id);

        $properties = $jsonLDEntity->getProperties();
        $this->marshalMetadataProperties($metadata, $properties);

        $forceIDProperty = $metadata->getForceIDProperty();
        if ($forceIDProperty && ! $properties->has('id')) {
            $property = $this->marshalPropertyFromMetadata(
                $metadata,
                $object,
                $id,
                $metadata->getRouteIdentifierName()
            );
            $properties->add($property);
        }

        return $jsonLDEntity;
    }

    /**
     * @param  object $object
     * @param  Metadata $metadata
     * @return Collection
     */
    public function createCollectionFromMetadata($object, Metadata $metadata)
    {
        $jsonLDCollection = new Collection($object);
        $jsonLDCollection->setCollectionName($metadata->getCollectionName());
        $jsonLDCollection->setCollectionRoute($metadata->getRoute());
        $jsonLDCollection->setEntityRoute($metadata->getEntityRoute());
        $jsonLDCollection->setRouteIdentifierName($metadata->getRouteIdentifierName());
        $jsonLDCollection->setEntityIdentifierName($metadata->getEntityIdentifierName());

        $properties = $jsonLDCollection->getProperties();
        $this->marshalMetadataProperties($metadata, $properties);

        $forceIDProperty = $metadata->getForceIDProperty();
        if ($forceIDProperty && ! $properties->has('id')
            && ($metadata->hasUrl() || $metadata->hasRoute())
        ) {
            $property = $this->marshalPropertyFromMetadata($metadata, $object);
            $properties->add($property);
        }

        return $jsonLDCollection;
    }

    /**
     * Creates a property object, given metadata and a resource
     *
     * @param  Metadata $metadata
     * @param  object $object
     * @param  null|string $id
     * @param  null|string $routeIdentifierName
     * @param  string $relation
     * @return Property
     * @throws Exception\RuntimeException
     */
    public function marshalPropertyFromMetadata(
        Metadata $metadata,
        $object,
        $id = null,
        $routeIdentifierName = null,
        $relation = 'id'
    ) {
        $property = new Property($relation);
        if ($metadata->hasUrl()) {
            $property->setUrl($metadata->getUrl());
            return $property;
        }

        if (! $metadata->hasRoute()) {
            throw new Exception\RuntimeException(sprintf(
                'Unable to create a self property for resource of type "%s"; metadata does not contain a route or a url',
                get_class($object)
            ));
        }

        $params = $metadata->getRouteParams();

        // process any callbacks
        foreach ($params as $key => $param) {
            // bind to the object if supported
            if ($param instanceof Closure
                && version_compare(PHP_VERSION, '5.4.0') >= 0
            ) {
                $param = $param->bindTo($object);
            }

            // pass the object for callbacks and non-bound closures
            if (is_callable($param)) {
                $params[$key] = call_user_func_array($param, [$object]);
            }
        }

        if ($routeIdentifierName) {
            $params = array_merge($params, [$routeIdentifierName => $id]);
        }

        $property->setRoute($metadata->getRoute(), $params, $metadata->getRouteOptions());
        return $property;
    }

    /**
     * Inject any properties found in the metadata into the resource's property collection
     *
     * @param  Metadata $metadata
     * @param  PropertyCollection $properties
     */
    public function marshalMetadataProperties(Metadata $metadata, PropertyCollection $properties)
    {
        foreach ($metadata->getProperties() as $propertyData) {
            $property = Property::factory($propertyData);
            $properties->add($property);
        }
    }
}
