<?php
namespace Increazy\ApiExtenderV2\Observer;

use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\Event\ObserverInterface;

use Psr\Log\LoggerInterface as Logger;
use Magento\Swatches\Helper\Data as SwatchHelper;
use Magento\Eav\Api\Data\AttributeInterface;

class OptionSwatchLoadAfter implements ObserverInterface
{
    /**
     * @var Logger
     */
    protected $_logger;

    protected $_swatchHelper;

    public function __construct(Logger $logger, SwatchHelper $swatchHelper)
    {
        $this->_logger = $logger;
        $this->_swatchHelper = $swatchHelper;
    }
    /**
     * @param EventObserver $observer
     * @return $this
     * @throws \Exception
     */
    public function execute(EventObserver $observer)
    {
        $attribute = $observer->getEvent()->getData('attribute');

        $extensionAttributes = $attribute->getExtensionAttributes();

        //isVisualSwatch
        if ($this->_swatchHelper->isVisualSwatch($attribute))
        {
            $options = $attribute->getOptions();
            $options = $this->processOptions($options);
            if (method_exists($extensionAttributes, 'setProductAttributeOptionSwatch')) {
                $extensionAttributes->setProductAttributeOptionSwatch($options);
                $attribute->setExtensionAttributes($extensionAttributes);
            }
        }

        return $this;
    }
    private function processOptions($options)
    {
        $option_ids = array();
        foreach($options as $option)
        {
            $option_ids[] = $option->getValue();
        }

        $swatches = $this->_swatchHelper->getSwatchesByOptionsId($option_ids);

        if (count($swatches) > 0)
        {
            foreach($options as $option)
            {
                if (isset($swatches[$option->getValue()]))
                {
                    $swatch = $swatches[$option->getValue()];
                    $option->setType($swatch['type']);
                    $option->setSwatchValue($swatch['value']);
                }
            }
        }

        return $options;
    }
}
