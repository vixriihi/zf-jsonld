<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZF\JsonLD\Plugin;

use ArrayObject;
use Countable;
use Zend\EventManager\EventManager;
use Zend\EventManager\EventManagerAwareInterface;
use Zend\EventManager\EventManagerInterface;
use Zend\Hydrator\ExtractionInterface;
use Zend\Hydrator\HydratorPluginManager;
use Zend\Mvc\Controller\Plugin\PluginInterface as ControllerPluginInterface;
use Zend\Paginator\Paginator;
use Zend\Stdlib\ArrayUtils;
use Zend\Stdlib\DispatchableInterface;
use Zend\View\Helper\AbstractHelper;
use Zend\View\Helper\ServerUrl;
use Zend\View\Helper\Url;
use ZF\ApiProblem\ApiProblem;
use ZF\JsonLD\Collection;
use ZF\JsonLD\Entity;
use ZF\JsonLD\EntityHydratorManager;
use ZF\JsonLD\Extractor\EntityExtractor;
use ZF\JsonLD\Exception;
use ZF\JsonLD\Extractor\PropertyCollectionExtractor;
use ZF\JsonLD\Extractor\PropertyCollectionExtractorInterface;
use ZF\JsonLD\Property\Property;
use ZF\JsonLD\Property\PropertyCollection;
use ZF\JsonLD\Property\PropertyCollectionAwareInterface;
use ZF\JsonLD\Property\PaginationInjector;
use ZF\JsonLD\Property\PaginationInjectorInterface;
use ZF\JsonLD\Metadata\Metadata;
use ZF\JsonLD\Metadata\MetadataMap;
use ZF\JsonLD\Resource;
use ZF\JsonLD\ResourceFactory;

/**
 * Generate properties for use with JsonLD payloads
 */
class JsonLD extends AbstractHelper implements
    ControllerPluginInterface,
    EventManagerAwareInterface
{
    /**
     * @var DispatchableInterface
     */
    protected $controller;

    /**
     * @var ResourceFactory
     */
    protected $resourceFactory;

    /**
     * @var EntityHydratorManager
     */
    protected $entityHydratorManager;

    /**
     * @var EntityExtractor
     */
    protected $entityExtractor;

    /**
     * Boolean to render embedded entities or just include member data
     *
     * @var boolean
     */
    protected $renderEmbeddedEntities = true;

    /**
     * Boolean to render collections or just return their member data
     *
     * @var boolean
     */
    protected $renderCollections = true;

    /**
     * @var EventManagerInterface
     */
    protected $events;

    /**
     * @var HydratorPluginManager
     */
    protected $hydrators;

    /**
     * @var MetadataMap
     */
    protected $metadataMap;

    /**
     * @var PaginationInjectorInterface
     */
    protected $paginationInjector;

    /**
     * @var ServerUrl
     */
    protected $serverUrlHelper;

    /**
     * @var Url
     */
    protected $urlHelper;

    /**
     * @var PropertyCollectionExtractor
     */
    protected $propertyCollectionExtractor;

    /**
     * Entities spl hash stack for circular reference detection
     *
     * @var array
     */
    protected $entityHashStack = [];

    /**
     * @param null|HydratorPluginManager $hydrators
     */
    public function __construct(HydratorPluginManager $hydrators = null)
    {
        if (null === $hydrators) {
            $hydrators = new HydratorPluginManager();
        }
        $this->hydrators = $hydrators;
    }

    /**
     * @param DispatchableInterface $controller
     */
    public function setController(DispatchableInterface $controller)
    {
        $this->controller = $controller;
    }

    /**
     * @return DispatchableInterface
     */
    public function getController()
    {
        return $this->controller;
    }

    /**
     * Retrieve the event manager instance
     *
     * Lazy-initializes one if none present.
     *
     * @return EventManagerInterface
     */
    public function getEventManager()
    {
        if (! $this->events) {
            $this->setEventManager(new EventManager());
        }
        return $this->events;
    }

    /**
     * Set the event manager instance
     *
     * @param  EventManagerInterface $events
     * @return JsonLD
     */
    public function setEventManager(EventManagerInterface $events)
    {
        $events->setIdentifiers([
            __CLASS__,
            get_class($this),
        ]);
        $this->events = $events;

        $events->attach('getIdFromEntity', function ($e) {
            $entity = $e->getParam('entity');

            // Found id in array
            if (is_array($entity)) {
                if (array_key_exists('id', $entity)) {
                    return $entity['id'];
                }
            }

            // No id in array, or not an object; return false
            if (is_array($entity) || !is_object($entity)) {
                return false;
            }

            // Found public id property on object
            if (isset($entity->id)) {
                return $entity->id;
            }

            // Found public id getter on object
            if (method_exists($entity, 'getid')) {
                return $entity->getId();
            }

            // not found
            return false;
        });

        return $this;
    }

    /**
     * @return ResourceFactory
     */
    public function getResourceFactory()
    {
        if (! $this->resourceFactory instanceof ResourceFactory) {
            $this->resourceFactory = new ResourceFactory(
                $this->getEntityHydratorManager(),
                $this->getEntityExtractor()
            );
        }
        return $this->resourceFactory;
    }

    /**
     * @param  ResourceFactory $factory
     * @return JsonLD
     */
    public function setResourceFactory(ResourceFactory $factory)
    {
        $this->resourceFactory = $factory;
        return $this;
    }

    /**
     * @return EntityHydratorManager
     */
    public function getEntityHydratorManager()
    {
        if (! $this->entityHydratorManager instanceof EntityHydratorManager) {
            $this->entityHydratorManager = new EntityHydratorManager(
                $this->hydrators,
                $this->getMetadataMap()
            );
        }

        return $this->entityHydratorManager;
    }

    /**
     * @param  EntityHydratorManager $manager
     * @return JsonLD
     */
    public function setEntityHydratorManager(EntityHydratorManager $manager)
    {
        $this->entityHydratorManager = $manager;
        return $this;
    }

    /**
     * @return EntityExtractor
     */
    public function getEntityExtractor()
    {
        if (! $this->entityExtractor instanceof EntityExtractor) {
            $this->entityExtractor = new EntityExtractor(
                $this->getEntityHydratorManager()
            );
        }

        return $this->entityExtractor;
    }

    /**
     * @param  EntityExtractor $extractor
     * @return JsonLD
     */
    public function setEntityExtractor(EntityExtractor $extractor)
    {
        $this->entityExtractor = $extractor;
        return $this;
    }

    /**
     * @return HydratorPluginManager
     */
    public function getHydratorManager()
    {
        return $this->hydrators;
    }

    /**
     * @return MetadataMap
     */
    public function getMetadataMap()
    {
        if (! $this->metadataMap instanceof MetadataMap) {
            $this->setMetadataMap(new MetadataMap());
        }

        return $this->metadataMap;
    }

    /**
     * @param  MetadataMap $map
     * @return JsonLD
     */
    public function setMetadataMap(MetadataMap $map)
    {
        $this->metadataMap = $map;
        return $this;
    }

    /**
     * @return PaginationInjectorInterface
     */
    public function getPaginationInjector()
    {
        if (! $this->paginationInjector instanceof PaginationInjectorInterface) {
            $this->setPaginationInjector(new PaginationInjector());
        }
        return $this->paginationInjector;
    }

    /**
     * @param  PaginationInjectorInterface $injector
     * @return JsonLD
     */
    public function setPaginationInjector(PaginationInjectorInterface $injector)
    {
        $this->paginationInjector = $injector;
        return $this;
    }

    /**
     * @param ServerUrl $helper
     * @return JsonLD
     */
    public function setServerUrlHelper(ServerUrl $helper)
    {
        $this->serverUrlHelper = $helper;
        return $this;
    }

    /**
     * @param Url $helper
     * @return JsonLD
     */
    public function setUrlHelper(Url $helper)
    {
        $this->urlHelper = $helper;
        return $this;
    }

    /**
     * @return PropertyCollectionExtractorInterface
     */
    public function getPropertyCollectionExtractor()
    {
        return $this->propertyCollectionExtractor;
    }

    /**
     * @param  PropertyCollectionExtractorInterface $extractor
     * @return JsonLD
     */
    public function setPropertyCollectionExtractor(PropertyCollectionExtractorInterface $extractor)
    {
        $this->propertyCollectionExtractor = $extractor;
        return $this;
    }

    /**
     * Map an entity class to a specific hydrator instance
     *
     * @param  string $class
     * @param  ExtractionInterface $hydrator
     * @return JsonLD
     */
    public function addHydrator($class, $hydrator)
    {
        $this->getEntityHydratorManager()->addHydrator($class, $hydrator);
        return $this;
    }

    /**
     * Set the default hydrator to use if none specified for a class.
     *
     * @param  ExtractionInterface $hydrator
     * @return JsonLD
     */
    public function setDefaultHydrator(ExtractionInterface $hydrator)
    {
        $this->getEntityHydratorManager()->setDefaultHydrator($hydrator);
        return $this;
    }

    /**
     * Set boolean to render embedded entities or just include member data
     *
     * @param  boolean $value
     * @return JsonLD
     */
    public function setRenderEmbeddedEntities($value)
    {
        $this->renderEmbeddedEntities = $value;
        return $this;
    }

    /**
     * Get boolean to render embedded entities or just include member data
     *
     * @return boolean
     */
    public function getRenderEmbeddedEntities()
    {
        return $this->renderEmbeddedEntities;
    }

    /**
     * Set boolean to render embedded collections or just include member data
     *
     * @param  boolean $value
     * @return JsonLD
     */
    public function setRenderCollections($value)
    {
        $this->renderCollections = $value;
        return $this;
    }

    /**
     * Get boolean to render embedded collections or just include member data
     *
     * @return boolean
     */
    public function getRenderCollections()
    {
        return $this->renderCollections;
    }

    /**
     * Retrieve a hydrator for a given entity
     *
     * If the entity has a mapped hydrator, returns that hydrator. If not, and
     * a default hydrator is present, the default hydrator is returned.
     * Otherwise, a boolean false is returned.
     *
     * @param  object $entity
     * @return ExtractionInterface|false
     */
    public function getHydratorForEntity($entity)
    {
        return $this->getEntityHydratorManager()->getHydratorForEntity($entity);
    }

    /**
     * "Render" a Collection
     *
     * Injects pagination links, if the composed collection is a Paginator, and
     * then loops through the collection to create the data structure representing
     * the collection.
     *
     * For each entity in the collection, the event "renderCollection.entity" is
     * triggered, with the following parameters:
     *
     * - "collection", which is the $jsonLDCollection passed to the method
     * - "entity", which is the current entity
     * - "route", the resource route that will be used to generate links
     * - "routeParams", any default routing parameters/substitutions to use in URL assembly
     * - "routeOptions", any default routing options to use in URL assembly
     *
     * This event can be useful particularly when you have multi-segment routes
     * and wish to ensure that route parameters are injected, or if you want to
     * inject query or fragment parameters.
     *
     * Event parameters are aggregated in an ArrayObject, which allows you to
     * directly manipulate them in your listeners:
     *
     * <code>
     * $params = $e->getParams();
     * $params['routeOptions']['query'] = ['format' => 'json'];
     * </code>
     *
     * @param  Collection $jsonLDCollection
     * @return array|ApiProblem Associative array representing the payload to render;
     *     returns ApiProblem if error in pagination occurs
     */
    public function renderCollection(Collection $jsonLDCollection)
    {
        $this->getEventManager()->trigger(__FUNCTION__, $this, ['collection' => $jsonLDCollection]);
        $collection     = $jsonLDCollection->getCollection();
        $collectionName = $jsonLDCollection->getCollectionName();

        if ($collection instanceof Paginator) {
            $status = $this->injectPaginationProperties($jsonLDCollection);
            if ($status instanceof ApiProblem) {
                return $status;
            }
        }

        $metadataMap = $this->getMetadataMap();

        $maxDepth = is_object($collection) && $metadataMap->has($collection) ?
            $metadataMap->get($collection)->getMaxDepth() : null;

        $payload = $jsonLDCollection->getAttributes();
        $payload = ArrayUtils::merge($payload, $this->fromResource($jsonLDCollection));

        if (isset($payload[$collectionName])) {
            $payload[$collectionName] = ArrayUtils::merge(
                $payload[$collectionName],
                $this->extractCollection($jsonLDCollection, 0, $maxDepth)
            );
        } else {
            $payload[$collectionName] = $this->extractCollection($jsonLDCollection, 0, $maxDepth);
        }

        if ($collection instanceof Paginator) {
            if (!empty($payload['view'])) {
                $payload['view']['@type'] = 'PartialCollectionView';
                $payload['view']['itemsPerPage'] = isset($payload['itemsPerPage'])
                    ? $payload['itemsPerPage']
                    : $jsonLDCollection->getPageSize();
            }

            $payload['totalItems'] = isset($payload['totalItems'])
                ? $payload['totalItems']
                : (int) $collection->getTotalItemCount();
        } elseif (is_array($collection) || $collection instanceof Countable) {
            $payload['totalItems'] = isset($payload['totalItems'])
                ? $payload['totalItems']
                : count($collection);
        }
        $payload['@context'] = 'http://www.w3.org/ns/hydra/context.jsonld';
        $payload['@type']    = 'Collection';

        $payload = new ArrayObject($payload);
        $this->getEventManager()->trigger(
            __FUNCTION__ . '.post',
            $this,
            ['payload' => $payload, 'collection' => $jsonLDCollection]
        );

        return (array) $payload;
    }

    /**
     * Render an individual entity
     *
     * Creates a hash representation of the Entity. The entity is first
     * converted to an array, and its associated properties are injected as properties.
     * If any members of the entity are themselves
     * Entity objects, they are extracted into an "member" hash.
     *
     * @param  Entity $jsonLDEntity
     * @param  bool $renderEntity
     * @param  int $depth           depth of the current rendering recursion
     * @param  int $maxDepth        maximum rendering depth for the current metadata
     * @throws Exception\CircularReferenceException
     * @return array
     */
    public function renderEntity(Entity $jsonLDEntity, $renderEntity = true, $depth = 0, $maxDepth = null)
    {
        $this->getEventManager()->trigger(__FUNCTION__, $this, ['entity' => $jsonLDEntity]);
        $entity           = $jsonLDEntity->entity;
        $entityProperties = clone $jsonLDEntity->getProperties(); // Clone to prevent property duplication

        $metadataMap = $this->getMetadataMap();

        if (is_object($entity)) {
            if ($maxDepth === null && $metadataMap->has($entity)) {
                $maxDepth = $metadataMap->get($entity)->getMaxDepth();
            }

            if ($maxDepth === null) {
                $entityHash = spl_object_hash($entity);

                if (isset($this->entityHashStack[$entityHash])) {
                    // we need to clear the stack, as the exception may be caught and the plugin may be invoked again
                    $this->entityHashStack = [];
                    throw new Exception\CircularReferenceException(sprintf(
                        "Circular reference detected in '%s'. %s",
                        get_class($entity),
                        "Either set a 'max_depth' metadata attribute or remove the reference"
                    ));
                }

                $this->entityHashStack[$entityHash] = get_class($entity);
            }
        }

        if (! $renderEntity || ($maxDepth !== null && $depth > $maxDepth)) {
            $entity = [];
        }

        if (!is_array($entity)) {
            $entity = $this->getEntityExtractor()->extract($entity);
        }

        foreach ($entity as $key => $value) {
            if (is_object($value) && $metadataMap->has($value)) {
                $value = $this->getResourceFactory()->createEntityFromMetadata(
                    $value,
                    $metadataMap->get($value),
                    $this->getRenderEmbeddedEntities()
                );
            }

            if ($value instanceof Entity) {
                $this->extractEmbeddedEntity($entity, $key, $value, $depth + 1, $maxDepth);
            }
            if ($value instanceof Collection) {
                $this->extractEmbeddedCollection($entity, $key, $value, $depth + 1, $maxDepth);
            }
            if ($value instanceof Property) {
                // We have a property; add it to the entity if it's not already present.
                $entityProperties = $this->injectPropertyAsProperty($value, $entityProperties);
                unset($entity[$key]);
            }
            if ($value instanceof PropertyCollection) {
                foreach ($value as $property) {
                    $entityProperties = $this->injectPropertyAsProperty($property, $entityProperties);
                }
                unset($entity[$key]);
            }
        }

        $jsonLDEntity->setProperties($entityProperties);
        $entity = ArrayUtils::merge($entity, $this->fromResource($jsonLDEntity));

        $payload = new ArrayObject($entity);
        $this->getEventManager()->trigger(
            __FUNCTION__ . '.post',
            $this,
            ['payload' => $payload, 'entity' => $jsonLDEntity]
        );

        if (isset($entityHash)) {
            unset($this->entityHashStack[$entityHash]);
        }

        return $payload->getArrayCopy();
    }

    /**
     * Create a fully qualified URI for a property
     *
     * Triggers the "createProperty" event with the route, id, entity, and a set of
     * params that will be passed to the route; listeners can alter any of the
     * arguments, which will then be used by the method to generate the url.
     *
     * @param  string $route
     * @param  null|false|int|string $id
     * @param  null|mixed $entity
     * @return string
     */
    public function createProperty($route, $id = null, $entity = null)
    {
        $params             = new ArrayObject();
        $reUseMatchedParams = true;

        if (false === $id) {
            $reUseMatchedParams = false;
        } elseif (null !== $id) {
            $params['id'] = $id;
        }

        $events      = $this->getEventManager();
        $eventParams = $events->prepareArgs([
            'route'    => $route,
            'id'       => $id,
            'entity'   => $entity,
            'params'   => $params,
        ]);
        $events->trigger(__FUNCTION__, $this, $eventParams);

        $path = call_user_func(
            $this->urlHelper,
            $eventParams['route'],
            $params->getArrayCopy(),
            $reUseMatchedParams
        );

        if (substr($path, 0, 4) == 'http') {
            return $path;
        }

        return call_user_func($this->serverUrlHelper, $path);
    }

    /**
     * Create a URL from a Property
     *
     * @param  Property $propertyDefinition
     * @return array
     */
    public function fromProperty(Property $propertyDefinition)
    {
        $propertyExtractor = $this->propertyCollectionExtractor->getPropertyExtractor();

        return $propertyExtractor->extract($propertyDefinition);
    }

    /**
     * Generate HAL properties from a PropertyCollection
     *
     * @param  PropertyCollection $collection
     * @return array
     */
    public function fromPropertyCollection(PropertyCollection $collection)
    {
        return $this->propertyCollectionExtractor->extract($collection);
    }

    /**
     * Create HAL properties "object" from an entity or collection
     *
     * @param  PropertyCollectionAwareInterface $resource
     * @return array
     */
    public function fromResource(PropertyCollectionAwareInterface $resource)
    {
        return $this->fromPropertyCollection($resource->getProperties());
    }

    /**
     * Create a entity and/or collection based on a metadata map
     *
     * @param  object $object
     * @param  Metadata $metadata
     * @param  bool $renderEmbeddedEntities
     * @return Entity|Collection
     */
    public function createEntityFromMetadata($object, Metadata $metadata, $renderEmbeddedEntities = true)
    {
        return $this->getResourceFactory()->createEntityFromMetadata(
            $object,
            $metadata,
            $renderEmbeddedEntities
        );
    }

    /**
     * Create an Entity instance and inject it with a self relational property if necessary
     *
     * @param  Entity|array|object $entity
     * @param  string $route
     * @param  string $routeIdentifierName
     * @return Entity
     */
    public function createEntity($entity, $route, $routeIdentifierName)
    {
        $metadataMap = $this->getMetadataMap();

        if (is_object($entity) && $metadataMap->has($entity)) {
            $jsonLDEntity = $this->getResourceFactory()->createEntityFromMetadata(
                $entity,
                $metadataMap->get($entity)
            );
        } elseif (! $entity instanceof Entity) {
            $id = $this->getIdFromEntity($entity) ?: null;
            $jsonLDEntity = new Entity($entity, $id);
        } else {
            $jsonLDEntity = $entity;
        }

        $metadata = (! is_array($entity) && $metadataMap->has($entity))
            ? $metadataMap->get($entity)
            : false;

        if (! $metadata || ($metadata && $metadata->getForceIDProperty())) {
            $this->injectIDProperty($jsonLDEntity, $route, $routeIdentifierName);
        }

        return $jsonLDEntity;
    }

    /**
     * Creates a Collection instance with a self relational property if necessary
     *
     * @param  Collection|array|object $collection
     * @param  null|string $route
     * @return Collection
     */
    public function createCollection($collection, $route = null)
    {
        $metadataMap = $this->getMetadataMap();
        if (is_object($collection) && $metadataMap->has($collection)) {
            $collection = $this->getResourceFactory()->createCollectionFromMetadata(
                $collection,
                $metadataMap->get($collection)
            );
        }

        if (! $collection instanceof Collection) {
            $collection = new Collection($collection);
        }
        $this->injectIDProperty($collection, $route);

        return $collection;
    }

    /**
     * @param  object $object
     * @param  Metadata $metadata
     * @return Collection
     */
    public function createCollectionFromMetadata($object, Metadata $metadata)
    {
        return $this->getResourceFactory()->createCollectionFromMetadata($object, $metadata);
    }

    /**
     * Inject a "id"  based on the route and identifier
     *
     * @param  PropertyCollectionAwareInterface $resource
     * @param  string $route
     * @param  string $routeIdentifier
     */
    public function injectIDProperty(PropertyCollectionAwareInterface $resource, $route, $routeIdentifier = 'id')
    {
        $properties = $resource->getProperties();
        if ($properties->has('id')) {
            return;
        }

        $id = new Property('id');
        $id->setRoute($route);

        $routeParams  = [];
        $routeOptions = [];
        if ($resource instanceof Entity
            && null !== $resource->id
        ) {
            $routeParams = [
                $routeIdentifier => $resource->id,
            ];
        }
        if ($resource instanceof Collection) {
            $routeParams  = $resource->getCollectionRouteParams();
            $routeOptions = $resource->getCollectionRouteOptions();
        }

        if (!empty($routeParams)) {
            $id->setRouteParams($routeParams);
        }
        if (!empty($routeOptions)) {
            $id->setRouteOptions($routeOptions);
        }

        $properties->add($id, true);
    }

    /**
     * Generate JsonLD properties for a paginated collection
     *
     * @param  Collection $jsonLDCollection
     * @return boolean|ApiProblem
     */
    protected function injectPaginationProperties(Collection $jsonLDCollection)
    {
        return $this->getPaginationInjector()->injectPaginationProperties($jsonLDCollection);
    }

    /**
     * Extracts and renders an Entity and embeds it in the parent
     * representation
     *
     * Removes the key from the parent representation, and creates a
     * representation for the key in the member object.
     *
     * @param  array $parent
     * @param  string $key
     * @param  Entity $entity
     * @param  int $depth           depth of the current rendering recursion
     * @param  int $maxDepth        maximum rendering depth for the current metadata
     */
    protected function extractEmbeddedEntity(array &$parent, $key, Entity $entity, $depth = 0, $maxDepth = null)
    {
        // No need to increment depth for this call
        $parent[$key] = $this->renderEntity($entity, true, $depth, $maxDepth);
    }

    /**
     * Extracts and renders a Collection and embeds it in the parent
     * representation
     *
     * Removes the key from the parent representation, and creates a
     * representation for the key in the member object.
     *
     * @param  array      $parent
     * @param  string     $key
     * @param  Collection $collection
     * @param  int        $depth        depth of the current rendering recursion
     * @param  int        $maxDepth     maximum rendering depth for the current metadata
     */
    protected function extractEmbeddedCollection(
        array &$parent,
        $key,
        Collection $collection,
        $depth = 0,
        $maxDepth = null
    ) {
        $parent[$key] = $this->extractCollection($collection, $depth + 1, $maxDepth);
    }

    /**
     * Extract a collection as an array
     *
     * @param  Collection $jsonLDCollection
     * @param  int $depth                   depth of the current rendering recursion
     * @param  int $maxDepth                maximum rendering depth for the current metadata
     * @return array
     */
    protected function extractCollection(Collection $jsonLDCollection, $depth = 0, $maxDepth = null)
    {
        $collection           = [];
        $events               = $this->getEventManager();
        $routeIdentifierName  = $jsonLDCollection->getRouteIdentifierName();
        $entityRoute          = $jsonLDCollection->getEntityRoute();
        $entityRouteParams    = $jsonLDCollection->getEntityRouteParams();
        $entityRouteOptions   = $jsonLDCollection->getEntityRouteOptions();
        $metadataMap          = $this->getMetadataMap();
        $entityMetadata       = null;

        foreach ($jsonLDCollection->getCollection() as $entity) {
            $eventParams = new ArrayObject([
                'collection'   => $jsonLDCollection,
                'entity'       => $entity,
                'route'        => $entityRoute,
                'routeParams'  => $entityRouteParams,
                'routeOptions' => $entityRouteOptions,
            ]);
            $events->trigger('renderCollection.entity', $this, $eventParams);

            $entity = $eventParams['entity'];

            if (is_object($entity) && $metadataMap->has($entity)) {
                $entity = $this->getResourceFactory()->createEntityFromMetadata($entity, $metadataMap->get($entity));
            }

            if ($entity instanceof Entity) {
                // Depth does not increment at this level
                $collection[] = $this->renderEntity($entity, $this->getRenderCollections(), $depth, $maxDepth);
                continue;
            }

            if (!is_array($entity)) {
                $entity = $this->getEntityExtractor()->extract($entity);
            }

            foreach ($entity as $key => $value) {
                if (is_object($value) && $metadataMap->has($value)) {
                    $value = $this->getResourceFactory()->createEntityFromMetadata($value, $metadataMap->get($value));
                }

                if ($value instanceof Entity) {
                    $this->extractEmbeddedEntity($entity, $key, $value, $depth + 1, $maxDepth);
                }

                if ($value instanceof Collection) {
                    $this->extractEmbeddedCollection($entity, $key, $value, $depth + 1, $maxDepth);
                }
            }

            $id = $this->getIdFromEntity($entity);

            if ($id === false) {
                // Cannot handle entities without an identifier
                // Return as-is
                $collection[] = $entity;
                continue;
            }

            if ($eventParams['entity'] instanceof PropertyCollectionAwareInterface) {
                $properties = $eventParams['entity']->getProperties();
            } else {
                $properties = new PropertyCollection();
            }

            if (isset($entity['properties']) && $entity['properties'] instanceof PropertyCollection) {
                $properties = $entity['properties'];
            }

            if (!isset($entity['id'])) {
                $idProperty = new Property('id');
                $idProperty->setRoute(
                    $eventParams['route'],
                    array_merge($eventParams['routeParams'], [$routeIdentifierName => $id]),
                    $eventParams['routeOptions']
                );
                $properties->add($idProperty);
            }

            $entity = ArrayUtils::merge($entity , $this->fromPropertyCollection($properties));

            $collection[] = $entity;
        }

        return $collection;
    }

    /**
     * Retrieve the identifier from an entity
     *
     * Expects an "id" member to exist; if not, a boolean false is returned.
     *
     * Triggers the "getIdFromEntity" event with the entity; listeners can
     * return a non-false, non-null value in order to specify the identifier
     * to use for URL assembly.
     *
     * @param  array|object $entity
     * @return mixed|false
     */
    protected function getIdFromEntity($entity)
    {
        $params  = [
            'entity'   => $entity,
        ];

        $callback = function ($r) {
            return (null !== $r && false !== $r);
        };

        $results = $this->getEventManager()->trigger(
            __FUNCTION__,
            $this,
            $params,
            $callback
        );

        if ($results->stopped()) {
            return $results->last();
        }

        return false;
    }

    /**
     * Inject a property-based property into the property collection.
     *
     * Ensures that the property hasn't been previously injected.
     *
     * @param Property[]|Property $property
     * @param PropertyCollection $properties
     * @return PropertyCollection
     * @throws Exception\InvalidArgumentException if a non-property is provided.
     */
    protected function injectPropertyAsProperty($property, PropertyCollection $properties)
    {
        if (is_array($property)) {
            foreach ($property as $single) {
                $properties = $this->injectPropertyAsProperty($single, $properties);
            }
            return $properties;
        }

        if (! $property instanceof Property) {
            throw new Exception\InvalidArgumentException(
                'Invalid property discovered; cannot inject into representation'
            );
        }

        $rel = $property->getKeyword();
        if (! $properties->has($rel)) {
            $properties->add($property);
            return $properties;
        }

        $relProperty = $properties->get($rel);
        if ($relProperty !== $property
            || (is_array($relProperty) && ! in_array($property, $relProperty, true))
        ) {
            $properties->add($property);
            return $properties;
        }

        return $properties;
    }
}
