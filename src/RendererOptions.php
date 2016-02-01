<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZF\JsonLD;

use Zend\Stdlib\AbstractOptions;

class RendererOptions extends AbstractOptions
{
    /**
     * @var string
     */
    protected $defaultHydrator;

    /**
     * @var bool
     */
    protected $renderMemberEntities = true;

    /**
     * @var bool
     */
    protected $renderMemberCollections = true;

    /**
     * @var array
     */
    protected $hydrators = [];

    /**
     * @param string $hydrator
     */
    public function setDefaultHydrator($hydrator)
    {
        $this->defaultHydrator = $hydrator;
    }

    /**
     * @return string
     */
    public function getDefaultHydrator()
    {
        return $this->defaultHydrator;
    }

    /**
     * @param bool $flag
     */
    public function setRenderMemberEntities($flag)
    {
        $this->renderMemberEntities = (bool) $flag;
    }

    /**
     * @return string
     */
    public function getRenderMemberEntities()
    {
        return $this->renderMemberEntities;
    }

    /**
     * @param bool $flag
     */
    public function setRenderMemberCollections($flag)
    {
        $this->renderMemberCollections = (bool) $flag;
    }

    /**
     * @return string
     */
    public function getRenderMemberCollections()
    {
        return $this->renderMemberCollections;
    }

    /**
     * @param array $hydrators
     */
    public function setHydrators(array $hydrators)
    {
        $this->hydrators = $hydrators;
    }

    /**
     * @return string
     */
    public function getHydrators()
    {
        return $this->hydrators;
    }
}
