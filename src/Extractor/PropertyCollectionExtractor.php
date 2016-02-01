<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2015 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZF\JsonLD\Extractor;

use ZF\ApiProblem\Exception\DomainException;
use ZF\JsonLD\Property\Property;
use ZF\JsonLD\Property\PropertyCollection;

class PropertyCollectionExtractor implements PropertyCollectionExtractorInterface
{
    /**
     * @var PropertyExtractorInterface
     */
    protected $propertyExtractor;

    /**
     * @param PropertyExtractorInterface $propertyExtractor
     */
    public function __construct(PropertyExtractorInterface $propertyExtractor)
    {
        $this->setPropertyExtractor($propertyExtractor);
    }

    /**
     * @return PropertyExtractorInterface
     */
    public function getPropertyExtractor()
    {
        return $this->propertyExtractor;
    }

    /**
     * @param PropertyExtractorInterface $propertyExtractor
     */
    public function setPropertyExtractor(PropertyExtractorInterface $propertyExtractor)
    {
        $this->propertyExtractor = $propertyExtractor;
    }

    /**
     * @inheritDoc
     */
    public function extract(PropertyCollection $collection)
    {
        $properties = [];
        foreach ($collection as $keyword => $propertyDefinition) {
            if ($propertyDefinition instanceof Property) {
                $properties[$keyword] = $this->propertyExtractor->extract($propertyDefinition);
                continue;
            }
            if ($propertyDefinition instanceof PropertyCollection) {
                $properties[$keyword] = $this->extract($propertyDefinition);
                continue;
            }

            if (!is_array($propertyDefinition)) {
                throw new DomainException(sprintf(
                    'Property object for keyword "%s" in resource was malformed; cannot generate property',
                    $keyword
                ));
            }

            $aggregate = [];
            foreach ($propertyDefinition as $subProperty) {
                if (!$subProperty instanceof Property) {
                    throw new DomainException(sprintf(
                        'Property object aggregated for keyword "%s" in resource was malformed; cannot generate property',
                        $keyword
                    ));
                }

                $aggregate[] = $this->propertyExtractor->extract($subProperty);
            }
            $properties[$keyword] = count($aggregate)  < 2 ? current($aggregate) : $aggregate;
        }

        return $properties;
    }
}
