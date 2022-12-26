<?php
namespace Increazy\ApiExtenderV2\Observer;

class OrderPlaced implements \Magento\Framework\Event\ObserverInterface
{
    protected $catalogProductEditActionAttributeHelper;

    public function __construct(
        \Magento\Catalog\Helper\Product\Edit\Action\Attribute $catalogProductEditActionAttributeHelper
    ) {
        $this->catalogProductEditActionAttributeHelper = $catalogProductEditActionAttributeHelper;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $order = $observer->getEvent()->getOrder();
        if(!$order) { return; }

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $scopeConfig = $objectManager->get('Magento\Framework\App\Config\ScopeConfigInterface');

        $appID = $scopeConfig->getValue('increazy_general/general/app', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $isTest = $scopeConfig->getValue('increazy_general/general/test', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $env = $isTest ? '.test' : '';

        foreach ($order->getAllItems() as $item) {
            $id = $item->getProductId();

            $ch = curl_init('https://indexer' . $env . '.increazy.com/magento2/webhook/product');
            $payload = json_encode([
                'app'    => $appID,
                'action' => 'save',
                'entity' => $id,
            ]);

            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type:application/json']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            $result = curl_exec($ch);
            curl_close($ch);
        }
    }
}