<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2015 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZF\JsonLD\Extractor;

use ZF\JsonLD\Property\Property;

interface PropertyExtractorInterface
{
    /**
     * Extract a structured property array from a Property instance.
     *
     * @param Property $property
     * @return array
     */
    public function extract(Property $property);
}
