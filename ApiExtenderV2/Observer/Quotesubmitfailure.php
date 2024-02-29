<?php

namespace Increazy\ApiExtenderV2\Observer;

use Increazy\ApiExtenderV2\Model\WebClientInterface;
use Magento\Framework\Event\ObserverInterface;

class Quotesubmitfailure implements ObserverInterface
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

            $quote = $observer->getQuote();
            $ids = [];
            foreach ($quote->getAllItems() as $item) {
                $ids[] = $item->getProductId();
    
            }

            $this->webClient->initialize('product', __CLASS__);
            
            $payload = [
                'action' => 'save',
                'entity' => $ids,
            ];
    
            $this->webClient->pushWebhook($payload);

            
        } catch (\Exception $e) {}
    }
}
