<?php

namespace ZF\JsonLD;

use Traversable;
use Zend\Http\Response;
use Zend\Mvc\MvcEvent;
use ZF\ApiProblem\ApiProblem;
use ZF\ApiProblem\ApiProblemResponse;
use ZF\Rest\RestController as HalRestController;
use ZF\ApiProblem\Exception\DomainException;
use ZF\JsonLD\Entity as JsonLDEntity;
use ZF\JsonLD\Collection as JsonLDCollection;
use ZF\ContentNegotiation\ViewModel as ContentNegotiationViewModel;
use ZF\JsonLD\Exception\InvalidArgumentException as JsonLDInvalidArgumentException;

class RestController extends HalRestController
{
    /**
     * Name of the collections entry in a Collection
     *
     * @var string
     */
    protected $collectionName = 'member';

    /**
     * Handle the dispatch event
     *
     * Does several "pre-flight" checks:
     * - Raises an exception if no resource is composed.
     * - Raises an exception if no route is composed.
     * - Returns a 405 response if the current HTTP request method is not in
     *   $options
     *
     * When the dispatch is complete, it will check to see if an array was
     * returned; if so, it will cast it to a view model using the
     * AcceptableViewModelSelector plugin, and the $acceptCriteria property.
     *
     * @param  MvcEvent $e
     * @return mixed
     * @throws DomainException
     */
    public function onDispatch(MvcEvent $e)
    {
        if (! $this->getResource()) {
            throw new DomainException(sprintf(
                '%s requires that a %s\ResourceInterface object is composed; none provided',
                __CLASS__,
                __NAMESPACE__
            ));
        }

        if (! $this->route) {
            throw new DomainException(sprintf(
                '%s requires that a route name for the resource is composed; none provided',
                __CLASS__
            ));
        }

        // Check for an API-Problem in the event
        $return = $e->getParam('api-problem', false);

        // If no return value dispatch the parent event
        if (! $return) {
            $return = parent::onDispatch($e);
        }

        if (! $return instanceof ApiProblem
            && ! $return instanceof JsonLDEntity
            && ! $return instanceof JsonLDCollection
        ) {
            return $return;
        }

        if ($return instanceof ApiProblem) {
            return new ApiProblemResponse($return);
        }

        // Set the fallback content negotiation to use HalJson.
        $e->setParam('ZFContentNegotiationFallback', 'JsonLD');

        // Use content negotiation for creating the view model
        $viewModel = new ContentNegotiationViewModel(['payload' => $return]);
        $e->setResult($viewModel);

        return $viewModel;
    }

    /**
     * Create a new entity
     *
     * @param  array $data
     * @return Response|ApiProblem|ApiProblemResponse|JsonLDEntity
     */
    public function create($data)
    {
        $events = $this->getEventManager();
        $events->trigger('create.pre', $this, ['data' => $data]);

        try {
            $value = $this->getResource()->create($data);
        } catch (\Exception $e) {
            return $this->createApiProblemFromException($e);
        }

        if ($this->isPreparedResponse($value)) {
            return $value;
        }

        if ($value instanceof JsonLDCollection) {
            $jsonLDCollection = $this->prepareHalCollection($value);

            $events->trigger('create.post', $this, [
                'data'       => $data,
                'entity'     => $jsonLDCollection,
                'collection' => $jsonLDCollection,
                'resource'   => $jsonLDCollection,
            ]);

            return $jsonLDCollection;
        }

        $jsonLDEntity = $this->createHalEntity($value);

        if ($jsonLDEntity->getProperties()->has('@id')) {
            $plugin = $this->plugin('JsonLD');
            $idLink = $jsonLDEntity->getProperties()->get('@id');
            $idLinkUrl = $plugin->fromProperty($idLink);

            $response = $this->getResponse();
            $response->setStatusCode(201);
            $response->getHeaders()->addHeaderLine('Location', $idLinkUrl);
        }

        $events->trigger('create.post', $this, [
            'data'     => $data,
            'entity'   => $jsonLDEntity,
            'resource' => $jsonLDEntity,
        ]);

        return $jsonLDEntity;
    }

    /**
     * Unlike name suggests this prepare a JsonLD collection
     * with the metadata for the current instance.
     *
     * Chanign the name would meen that would have to override almost every method in this class
     *
     *
     * @param JsonLDCollection $collection
     * @return JsonLDCollection|ApiProblem
     */
    protected function prepareHalCollection(JsonLDCollection $collection)
    {
        if (! $collection->getProperties()->has('@id')) {
            $plugin = $this->plugin('JsonLD');
            $plugin->injectIDProperty($collection, $this->route);
        }

        $collection->setCollectionRoute($this->route);
        $collection->setRouteIdentifierName($this->getRouteIdentifierName());
        $collection->setEntityRoute($this->route);
        $collection->setCollectionName($this->collectionName);
        $collection->setPageSize($this->getPageSize());

        try {
            $collection->setPage($this->getRequest()->getQuery('page', 1));
        } catch (JsonLDInvalidArgumentException $e) {
            return new ApiProblem(400, $e->getMessage());
        }

        return $collection;
    }

    /**
     * Return collection of entities
     *
     * @return Response|JsonLDCollection
     */
    public function getList()
    {
        $events = $this->getEventManager();
        $events->trigger('getList.pre', $this, []);

        try {
            $collection = $this->getResource()->fetchAll();
        } catch (\Exception $e) {
            return $this->createApiProblemFromException($e);
        }

        if ($this->isPreparedResponse($collection)) {
            return $collection;
        }

        if (! is_array($collection)
            && ! $collection instanceof Traversable
            && ! $collection instanceof JsonLDCollection
            && is_object($collection)
        ) {
            $halEntity = $this->createHalEntity($collection);
            $events->trigger('getList.post', $this, ['collection' => $halEntity]);
            return $halEntity;
        }

        $pageSize = $this->pageSizeParam
            ? $this->getRequest()->getQuery($this->pageSizeParam, $this->pageSize)
            : $this->pageSize;

        if (isset($this->minPageSize) && $pageSize < $this->minPageSize) {
            return new ApiProblem(
                416,
                sprintf("Page size is out of range, minimum page size is %s", $this->minPageSize)
            );
        }

        if (isset($this->maxPageSize) && $pageSize > $this->maxPageSize) {
            return new ApiProblem(
                416,
                sprintf("Page size is out of range, maximum page size is %s", $this->maxPageSize)
            );
        }

        $this->setPageSize($pageSize);

        $jsonLDCollection = $this->createHalCollection($collection);

        if ($this->isPreparedResponse($jsonLDCollection)) {
            return $jsonLDCollection;
        }

        $events->trigger('getList.post', $this, [
            'collection' => $jsonLDCollection,
        ]);

        return $jsonLDCollection;
    }


    /**
     * Unlike name suggests this created JsonLDCollection
     *
     * @param  mixed $collection
     * @return JsonLDCollection
     */
    protected function createHalCollection($collection)
    {
        if (!$collection instanceof JsonLDCollection) {
            $jsonLDPlugin = $this->plugin('JsonLD');
            $collection = $jsonLDPlugin->createCollection($collection, $this->route);
        }

        return $this->prepareHalCollection($collection);
    }


    /**
     * Unlike name suggests this creates JsonLD entity
     *
     * @param  mixed $entity
     * @return JsonLDEntity
     */
    protected function createHalEntity($entity)
    {
        if ($entity instanceof JsonLDEntity &&
            ($entity->getProperties()->has('@id') || ! $entity->id)
        ) {
            return $entity;
        }

        $plugin = $this->plugin('JsonLD');

        return $plugin->createEntity(
            $entity,
            $this->route,
            $this->getRouteIdentifierName()
        );
    }

}