<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZFTest\JsonLD\Extractor;

use PHPUnit_Framework_TestCase as TestCase;
use Zend\Hydrator\HydratorPluginManager;
use ZF\JsonLD\EntityHydratorManager;
use ZF\JsonLD\Metadata\MetadataMap;
use ZFTest\JsonLD\Plugin\TestAsset;
use ZFTest\JsonLD\Plugin\TestAsset\DummyHydrator;

/**
 * @subpackage UnitTest
 */
class EntityHydratorManagerTest extends TestCase
{
    public function testAddHydratorGivenEntityClassAndHydratorInstanceShouldAssociateThem()
    {
        $entity        = new TestAsset\Entity('foo', 'Foo Bar');
        $hydratorClass = 'ZFTest\JsonLD\Plugin\TestAsset\DummyHydrator';
        $hydrator      = new $hydratorClass();

        $metadataMap = new MetadataMap();
        $hydratorPluginManager = new HydratorPluginManager();
        $entityHydratorManager = new EntityHydratorManager($hydratorPluginManager, $metadataMap);

        $entityHydratorManager->addHydrator(
            'ZFTest\JsonLD\Plugin\TestAsset\Entity',
            $hydrator
        );

        $entityHydrator = $entityHydratorManager->getHydratorForEntity($entity);
        $this->assertInstanceOf($hydratorClass, $entityHydrator);
        $this->assertSame($hydrator, $entityHydrator);
    }

    public function testAddHydratorGivenEntityAndHydratorClassesShouldAssociateThem()
    {
        $entity        = new TestAsset\Entity('foo', 'Foo Bar');
        $hydratorClass = 'ZFTest\JsonLD\Plugin\TestAsset\DummyHydrator';

        $metadataMap = new MetadataMap();
        $hydratorPluginManager = new HydratorPluginManager();
        $entityHydratorManager = new EntityHydratorManager($hydratorPluginManager, $metadataMap);

        $entityHydratorManager->addHydrator(
            'ZFTest\JsonLD\Plugin\TestAsset\Entity',
            $hydratorClass
        );

        $this->assertInstanceOf(
            $hydratorClass,
            $entityHydratorManager->getHydratorForEntity($entity)
        );
    }

    public function testAddHydratorDoesntFailWithAutoInvokables()
    {
        $metadataMap           = new MetadataMap();
        $hydratorPluginManager = new HydratorPluginManager();
        $entityHydratorManager = new EntityHydratorManager($hydratorPluginManager, $metadataMap);

        $entityHydratorManager->addHydrator('stdClass', 'ZFTest\JsonLD\Plugin\TestAsset\DummyHydrator');

        $this->assertInstanceOf(
            'ZFTest\JsonLD\Plugin\TestAsset\DummyHydrator',
            $entityHydratorManager->getHydratorForEntity(new \stdClass)
        );
    }

    public function testGetHydratorForEntityGivenEntityDefinedInMetadataMapShouldReturnDefaultHydrator()
    {
        $entity        = new TestAsset\Entity('foo', 'Foo Bar');
        $hydratorClass = 'ZFTest\JsonLD\Plugin\TestAsset\DummyHydrator';

        $metadataMap = new MetadataMap([
            'ZFTest\JsonLD\Plugin\TestAsset\Entity' => [
                'hydrator' => $hydratorClass,
            ],
        ]);

        $hydratorPluginManager = new HydratorPluginManager();
        $entityHydratorManager = new EntityHydratorManager($hydratorPluginManager, $metadataMap);

        $this->assertInstanceOf(
            $hydratorClass,
            $entityHydratorManager->getHydratorForEntity($entity)
        );
    }

    public function testGetHydratorForEntityGivenUnkownEntityShouldReturnDefaultHydrator()
    {
        $entity = new TestAsset\Entity('foo', 'Foo Bar');
        $defaultHydrator = new DummyHydrator();

        $metadataMap           = new MetadataMap();
        $hydratorPluginManager = new HydratorPluginManager();
        $entityHydratorManager = new EntityHydratorManager($hydratorPluginManager, $metadataMap);

        $entityHydratorManager->setDefaultHydrator($defaultHydrator);

        $entityHydrator = $entityHydratorManager->getHydratorForEntity($entity);

        $this->assertSame($defaultHydrator, $entityHydrator);
    }

    public function testGetHydratorForEntityGivenUnkownEntityAndNoDefaultHydratorDefinedShouldReturnFalse()
    {
        $entity = new TestAsset\Entity('foo', 'Foo Bar');

        $metadataMap           = new MetadataMap();
        $hydratorPluginManager = new HydratorPluginManager();
        $entityHydratorManager = new EntityHydratorManager($hydratorPluginManager, $metadataMap);

        $hydrator = $entityHydratorManager->getHydratorForEntity($entity);

        $this->assertFalse($hydrator);
    }
}
