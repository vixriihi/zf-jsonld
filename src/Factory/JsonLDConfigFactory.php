<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZF\JsonLD\Factory;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

class JsonLDConfigFactory implements FactoryInterface
{
    /**
     * @param  ServiceLocatorInterface $serviceLocator
     * @return array
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $config = [];
        if ($serviceLocator->has('config')) {
            $config = $serviceLocator->get('config');
        }

        $jsonLDConfig = [];
        if (isset($config['zf-jsonld']) && is_array($config['zf-jsonld'])) {
            $jsonLDConfig = $config['zf-jsonld'];
        }

        return $jsonLDConfig;
    }
}
