<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZF\JsonLD\View;

use ZF\JsonLD\Collection;
use ZF\JsonLD\Entity;
use Zend\View\Model\JsonModel;

/**
 * Simple extension to facilitate the specialized JsonStrategy and JsonRenderer
 * in this Module.
 */
class JsonLDModel extends JsonModel
{
    /**
     * @var bool
     */
    protected $terminate = true;

    /**
     * Does the payload represent a JsonLD collection?
     *
     * @return bool
     */
    public function isCollection()
    {
        $payload = $this->getPayload();
        return ($payload instanceof Collection);
    }

    /**
     * Does the payload represent a HAL entity?
     *
     * @return bool
     */
    public function isEntity()
    {
        $payload = $this->getPayload();
        return ($payload instanceof Entity);
    }

    /**
     * Set the payload for the response
     *
     * This is the value to represent in the response.
     *
     * @param  mixed $payload
     * @return JsonLDModel
     */
    public function setPayload($payload)
    {
        $this->setVariable('payload', $payload);
        return $this;
    }

    /**
     * Retrieve the payload for the response
     *
     * @return mixed
     */
    public function getPayload()
    {
        return $this->getVariable('payload');
    }

    /**
     * Override setTerminal()
     *
     * Does nothing; does not allow re-setting "terminate" flag.
     *
     * @param  bool $flag
     * @return JsonLDModel
     */
    public function setTerminal($flag = true)
    {
        return $this;
    }
}
