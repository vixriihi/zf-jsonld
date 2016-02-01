<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZF\JsonLD\Property;

use ZF\JsonLD\Collection;

interface PaginationInjectorInterface
{
    /**
     * Generate Hydra Json-LD for a paginated collection
     *
     * @param  Collection $jsonLDCollection
     * @return boolean|ApiProblem
     */
    public function injectPaginationProperties(Collection $jsonLDCollection);
}
