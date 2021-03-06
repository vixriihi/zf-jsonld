<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2015 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZF\JsonLD\Extractor;

use Zend\View\Helper\Url;
use Zend\View\Helper\ServerUrl;
use ZF\ApiProblem\Exception\DomainException;
use ZF\JsonLD\Property\Property;
use ZF\JsonLD\Property\PropertyCollection;

class PropertyExtractor implements PropertyExtractorInterface
{
    /**
     * @var ServerUrl
     */
    protected $serverUrlHelper;

    /**
     * @var Url
     */
    protected $urlHelper;

    /**
     * @var string
     */
    protected $serverUrlString;

    /**
     * @param  ServerUrl $serverUrlHelper
     * @param  Url $urlHelper
     */
    public function __construct(ServerUrl $serverUrlHelper, Url $urlHelper)
    {
        $this->serverUrlHelper = $serverUrlHelper;
        $this->urlHelper       = $urlHelper;
    }

    /**
     * @return string
     */
    protected function getServerUrl()
    {
        if ($this->serverUrlString === null) {
            $this->serverUrlString = call_user_func($this->serverUrlHelper);
        }
        return $this->serverUrlString;
    }

    /**
     * @inheritDoc
     */
    public function extract(Property $object)
    {
        if (!$object->isComplete()) {
            throw new DomainException(sprintf(
                'Property from resource provided to %s was incomplete; must contain a URL or a route',
                __METHOD__
            ));
        }

        if ($object->hasValue()) {
            $value = $object->getValue();
            if ($value instanceof PropertyCollection) {
                $extractor = new PropertyCollectionExtractor($this);
                $value = $extractor->extract($value);
            }
            return $value;
        }

        if ($object->hasUrl()) {
            return $object->getUrl();
        }

        $reuseMatchedParams = true;
        $options = $object->getRouteOptions();
        if (isset($options['reuse_matched_params'])) {
            $reuseMatchedParams = (bool) $options['reuse_matched_params'];
            unset($options['reuse_matched_params']);
        }

        $path = call_user_func(
            $this->urlHelper,
            $object->getRoute(),
            $object->getRouteParams(),
            $options,
            $reuseMatchedParams
        );

        if (substr($path, 0, 4) == 'http') {
            return $path;
        }
        return $this->getServerUrl() . $path;
    }
}
