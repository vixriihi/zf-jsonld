<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

return [
    'zf-jsonld' => [
        'renderer' => [
            // 'default_hydrator' => 'Hydrator Service Name',
            // 'hydrators'        => [
            //     class to hydrate/hydrator service name pairs
            // ],
        ],
        'metadata_map' => [
            // 'Class Name' => [
            //     'hydrator'        => 'Hydrator Service Name, if a resource',
            //     'entity_identifier_name' => 'identifying field name, if a resource',
            //     'route_name'      => 'name of route for this resource',
            //     'is_collection'   => 'boolean; set to true for collections',
            //     'properties'      => [
            //         [
            //             'key'   => 'property keyword',
            //             'value' => 'mixed value', // OR
            //             'url'   => 'string absolute URI to use', // OR
            //             'route' => [
            //                 'name'    => 'route name for this property',
            //                 'params'  => [ /* any route params to use for link generation */ ],
            //                 'options' => [ /* any options to pass to the router */ ],
            //             ],
            //         ],
            //         repeat as needed for any additional relational links you want for this resource
            //     ],
            //     'resource_route_name' => 'route name for embedded resources of a collection',
            //     'route_params'        => [ /* any route params to use for link generation */ ],
            //     'route_options'       => [ /* any options to pass to the router */ ],
            //     'url'                 => 'specific URL to use with this resource, if not using a route',
            // ],
            // repeat as needed for each resource/collection type
        ],
        'options' => [
            // Needed for generate valid _link url when you use a proxy
            'use_proxy' => false,
        ],
    ],
    // Creates a "JsonLD" selector for zfcampus/zf-content-negotiation
    'zf-content-negotiation' => [
        'selectors' => [
            'JsonLD' => [
                'ZF\JsonLD\View\JsonLDModel' => [
                    'application/json',
                    'application/*+json',
                ],
            ],
        ],
    ],
    'service_manager' => [
        'factories' => [
            'ZF\JsonLD\JsonLDConfig'    => 'ZF\JsonLD\Factory\JsonLDConfigFactory',
            'ZF\JsonLD\JsonLDRenderer'  => 'ZF\JsonLD\Factory\JsonLDRendererFactory',
            'ZF\JsonLD\JsonLDStrategy'  => 'ZF\JsonLD\Factory\JsonLDStrategyFactory',
            'ZF\JsonLD\MetadataMap'     => 'ZF\JsonLD\Factory\MetadataMapFactory',
            'ZF\JsonLD\RendererOptions' => 'ZF\JsonLD\Factory\RendererOptionsFactory',
        ],
    ],
    'view_helpers' => [
        'factories' => [
            'JsonLD' => 'ZF\JsonLD\Factory\JsonLDViewHelperFactory',
        ],
    ],
    'controller_plugins' => [
        'factories' => [
            'JsonLD' => 'ZF\JsonLD\Factory\JsonLDControllerPluginFactory',
        ],
    ],
];
