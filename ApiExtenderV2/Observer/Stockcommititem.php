<?php

namespace Increazy\ApiExtenderV2\Observer;

use Increazy\ApiExtenderV2\Model\WebClientInterface;
use Magento\Framework\Event\ObserverInterface;

class Stockcommititem implements ObserverInterface
{
    /**
     * @param WebClientInterface
     */
    private $webClient;

    
    public function __construct(WebClientInterface $webClient)
    {
        $this->webClient = $webClient;
    }
    
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        try {
           
            $item = $observer->getItem();
            $id = $item->getProductId();

            $this->webClient->initialize('product', __CLASS__);
            
            $payload = [
                'action' => 'save',
                'entity' => $id,
            ];
    
            $this->webClient->pushWebhook($payload);
            
        } catch (\Exception $e) {}
    }
}
