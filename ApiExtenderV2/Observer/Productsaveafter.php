<?php

namespace Increazy\ApiExtenderV2\Observer;

use Increazy\ApiExtenderV2\Model\WebClientInterface;
use Magento\Framework\Event\ObserverInterface;

class Productsaveafter implements ObserverInterface
{
   /**
     * @param WebClientInterface
     */
    private $webClient;


    private $hasExecuted = false;
    
    public function __construct(WebClientInterface $webClient)
    {
        $this->webClient = $webClient;
    }
    
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        try {
            if ($this->hasExecuted)
            {
                return $this;
            }
            $product = $observer->getProduct();
            $id = $product->getId();
            $this->webClient->initialize('product', __CLASS__);
            
            $payload = [
                'action' => 'save',
                'entity' => $id,
            ];
    
            $this->webClient->pushWebhook($payload);

            $this->hasExecuted = true;
        } catch (\Exception $e) {}
    }
}
