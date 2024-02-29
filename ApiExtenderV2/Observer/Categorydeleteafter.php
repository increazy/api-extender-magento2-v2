<?php

namespace Increazy\ApiExtenderV2\Observer;

use Increazy\ApiExtenderV2\Model\WebClientInterface;
use Magento\Framework\Event\ObserverInterface;

class Categorydeleteafter implements ObserverInterface
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

            $this->webClient->initialize('category', __CLASS__);
            $category = $observer->getCategory();
            $id = $category->getId();
            $payload = [
                'action' => 'delete',
                'entity' => $id,
            ];

            $this->webClient->pushWebhook($payload);
        } catch (\Exception $e) {}
    }
}
