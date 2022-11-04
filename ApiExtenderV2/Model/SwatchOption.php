<?php
namespace Increazy\ApiExtenderV2\Model;

use Increazy\ApiExtenderV2\Attribute\SwatchOptionInterface;
use Magento\Eav\Model\Entity\Attribute\Option;

class SwatchOption extends Option implements SwatchOptionInterface
{
    /**
     * Resource initialization
     *
     * @return void
     */
    public function _construct()
    {
        parent::__construct();
    }

    public function getType()
    {
        return $this->getData(SwatchOptionInterface::TYPE);
    }

    public function getSwatchValue()
    {
        return $this->getData(SwatchOptionInterface::SWATCHVALUE);
    }

    public function setType($type)
    {
        return $this->setData(SwatchOptionInterface::TYPE, $type);
    }

    public function setSwatchValue($value)
    {
        return $this->setData(SwatchOptionInterface::SWATCHVALUE, $swatch_value);
    }
}
