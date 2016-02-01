<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZF\JsonLD\Factory;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use ZF\JsonLD\RendererOptions;

class RendererOptionsFactory implements FactoryInterface
{
    /**
     * @param  ServiceLocatorInterface $serviceLocator
     * @return RendererOptions
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $config = $serviceLocator->get('ZF\JsonLD\JsonLDConfig');

        $rendererConfig = [];
        if (isset($config['renderer']) && is_array($config['renderer'])) {
            $rendererConfig = $config['renderer'];
        }

        return new RendererOptions($rendererConfig);
    }
}
