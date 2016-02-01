<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZFTest\JsonLD\Factory;

use PHPUnit_Framework_TestCase as TestCase;
use ZF\JsonLD\Factory\MetadataMapFactory;

class MetadataMapFactoryTest extends TestCase
{
    public function testInstantiatesMetadataMapWithEmptyConfig()
    {
        $services = $this->getMock('Zend\ServiceManager\ServiceLocatorInterface');

        $services
            ->expects($this->at(0))
            ->method('get')
            ->with('ZF\JsonLD\JsonLDConfig')
            ->will($this->returnValue([]));

        $services
            ->expects($this->at(1))
            ->method('has')
            ->with('HydratorManager')
            ->will($this->returnValue(false));

        $factory = new MetadataMapFactory();
        $renderer = $factory->createService($services);

        $this->assertInstanceOf('ZF\JsonLD\Metadata\MetadataMap', $renderer);
    }

    public function testInstantiatesMetadataMapWithMetadataMapConfig()
    {
        $services = $this->getMock('Zend\ServiceManager\ServiceLocatorInterface');

        $config = [
            'metadata_map' => [
                'ZFTest\JsonLD\Plugin\TestAsset\Entity' => [
                    'hydrator'   => 'Zend\Hydrator\ObjectProperty',
                    'route_name' => 'hostname/resource',
                    'route_identifier_name' => 'id',
                    'entity_identifier_name' => 'id',
                ],
                'ZFTest\JsonLD\Plugin\TestAsset\EmbeddedEntity' => [
                    'hydrator' => 'Zend\Hydrator\ObjectProperty',
                    'route'    => 'hostname/embedded',
                    'route_identifier_name' => 'id',
                    'entity_identifier_name' => 'id',
                ],
                'ZFTest\JsonLD\Plugin\TestAsset\EmbeddedEntityWithCustomIdentifier' => [
                    'hydrator'        => 'Zend\Hydrator\ObjectProperty',
                    'route'           => 'hostname/embedded_custom',
                    'route_identifier_name' => 'custom_id',
                    'entity_identifier_name' => 'custom_id',
                ],
            ],
        ];

        $services
            ->expects($this->at(0))
            ->method('get')
            ->with('ZF\JsonLD\JsonLDConfig')
            ->will($this->returnValue($config));

        $services
            ->expects($this->at(1))
            ->method('has')
            ->with('HydratorManager')
            ->will($this->returnValue(false));

        $factory = new MetadataMapFactory();
        $metadataMap = $factory->createService($services);

        $this->assertInstanceOf('ZF\JsonLD\Metadata\MetadataMap', $metadataMap);

        foreach ($config['metadata_map'] as $key => $value) {
            $this->assertTrue($metadataMap->has($key));
            $this->assertInstanceOf('ZF\JsonLD\Metadata\Metadata', $metadataMap->get($key));
        }
    }
}
