<?php
namespace Increazy\ApiExtenderV2\Observer;

use Increazy\ApiExtenderV2\Model\WebClientInterface;

class OrderPlaced implements \Magento\Framework\Event\ObserverInterface
{
    protected $catalogProductEditActionAttributeHelper;

    /**
     * @param WebClientInterface
     */
    private $webClient;


    public function __construct(
        \Magento\Catalog\Helper\Product\Edit\Action\Attribute $catalogProductEditActionAttributeHelper,
        WebClientInterface $webClient
    ) {
        $this->catalogProductEditActionAttributeHelper = $catalogProductEditActionAttributeHelper;
        $this->webClient = $webClient;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $order = $observer->getEvent()->getOrder();
        
        if(!$order) { return; }
        $ids = [];
        foreach ($order->getAllItems() as $item) {
            $ids[] = $item->getProductId();
        }
        $this->webClient->initialize('product', __CLASS__);
            
        $payload = [
            'action' => 'save',
            'entity' => $ids,
        ];

        $this->webClient->pushWebhook($payload);
        
        
    }
}