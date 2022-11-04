<?php
namespace Increazy\ApiExtenderV2\Attribute;

interface AssociativeArrayInterface
{
    const KEY = "key";
    const KEY_VALUE = "value";

    /**
     * Get key
     * 
     * @return string
     */
    public function getKey();

    /**
     * Set key
     * 
     * @return string
     */
    public function setKey($key);

    /**
     * Get value
     * 
     * @return array
     */
    public function getValue();

    /**
     * Set value
     * 
     * @param string
     * @return $this
     */
    public function setValue($searchCriteria);
}