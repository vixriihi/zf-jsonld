<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZF\JsonLD\Factory;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use ZF\JsonLD\View\JsonLDRenderer;

class JsonLDRendererFactory implements FactoryInterface
{
    /**
     * @param  ServiceLocatorInterface $serviceLocator
     * @return JsonLDRenderer
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $helpers            = $serviceLocator->get('ViewHelperManager');
        $apiProblemRenderer = $serviceLocator->get('ZF\ApiProblem\ApiProblemRenderer');

        $renderer = new JsonLDRenderer($apiProblemRenderer);
        $renderer->setHelperPluginManager($helpers);

        return $renderer;
    }
}
