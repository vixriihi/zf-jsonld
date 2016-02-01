<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZF\JsonLD\Factory;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use ZF\JsonLD\Exception;
use ZF\JsonLD\Extractor\PropertyCollectionExtractor;
use ZF\JsonLD\Extractor\PropertyExtractor;
use ZF\JsonLD\Plugin;

class JsonLDViewHelperFactory implements FactoryInterface
{
    /**
     * @param  ServiceLocatorInterface $serviceLocator
     * @return Plugin\JsonLD
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $services        = $serviceLocator->getServiceLocator();
        $jsonLDConfig       = $services->get('ZF\JsonLD\JsonLDConfig');
        /* @var $rendererOptions \ZF\JsonLD\RendererOptions */
        $rendererOptions = $services->get('ZF\JsonLD\RendererOptions');
        $metadataMap     = $services->get('ZF\JsonLD\MetadataMap');
        $hydrators       = $metadataMap->getHydratorManager();

        $serverUrlHelper = $serviceLocator->get('ServerUrl');
        if (isset($jsonLDConfig['options']['use_proxy'])) {
            $serverUrlHelper->setUseProxy($jsonLDConfig['options']['use_proxy']);
        }

        $urlHelper = $serviceLocator->get('Url');

        $helper = new Plugin\JsonLD($hydrators);
        $helper
            ->setMetadataMap($metadataMap)
            ->setServerUrlHelper($serverUrlHelper)
            ->setUrlHelper($urlHelper);

        $propertyExtractor = new PropertyExtractor($serverUrlHelper, $urlHelper);
        $propertyCollectionExtractor = new PropertyCollectionExtractor($propertyExtractor);
        $helper->setPropertyCollectionExtractor($propertyCollectionExtractor);

        $defaultHydrator = $rendererOptions->getDefaultHydrator();
        if ($defaultHydrator) {
            if (! $hydrators->has($defaultHydrator)) {
                throw new Exception\DomainException(sprintf(
                    'Cannot locate default hydrator by name "%s" via the HydratorManager',
                    $defaultHydrator
                ));
            }

            $hydrator = $hydrators->get($defaultHydrator);
            $helper->setDefaultHydrator($hydrator);
        }

        $helper->setRenderEmbeddedEntities($rendererOptions->getRenderMemberEntities());
        $helper->setRenderCollections($rendererOptions->getRenderMemberCollections());

        $hydratorMap = $rendererOptions->getHydrators();
        foreach ($hydratorMap as $class => $hydratorServiceName) {
            $helper->addHydrator($class, $hydratorServiceName);
        }

        return $helper;
    }
}
