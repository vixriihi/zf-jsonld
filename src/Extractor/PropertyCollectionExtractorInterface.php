<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2015 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZF\JsonLD\Extractor;

use ZF\JsonLD\Property\PropertyCollection;

interface PropertyCollectionExtractorInterface
{
    /**
     * Extract a property collection into a structured set of properties.
     *
     * @param PropertyCollection $collection
     * @return array
     */
    public function extract(PropertyCollection $collection);

    /**
     * @return PropertyExtractorInterface
     */
    public function getPropertyExtractor();
}
