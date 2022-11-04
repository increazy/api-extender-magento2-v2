<?php
namespace Increazy\ApiExtenderV2\Attribute;

use Magento\Eav\Api\Data\AttributeOptionInterface;

interface SwatchOptionInterface extends AttributeOptionInterface
{
    /**
     * Constants used as data array keys
     */
    const TYPE = 'type';
    const SWATCHVALUE = 'swatch_value';
    /**
     * Get option label
     *
     * @return string
     */
    public function getType();
    /**
     * Set option label
     *
     * @param string $label
     * @return $this
     */
    public function setType($type);
    /**
     * Get option value
     *
     * @return string
     */
    public function getSwatchValue();
    /**
     * Set option value
     *
     * @param string $value
     * @return string
     */
    public function setSwatchValue($swatch_value);
}
