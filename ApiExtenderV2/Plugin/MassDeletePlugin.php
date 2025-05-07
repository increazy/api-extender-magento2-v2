<?php

namespace Increazy\ApiExtenderV2\Plugin;

use Increazy\ApiExtenderV2\Model\WebClientInterface;

class MassDeletePlugin
{
    /**
     * @var WebClientInterface
     */
    private $webClient;

    public function __construct(WebClientInterface $webClient)
    {
        $this->webClient = $webClient;
    }

    public function afterExecute(\Magento\Catalog\Controller\Adminhtml\Product\MassDelete $subject, $result)
    {
        $ids = $subject->getRequest()->getParam('selected');

        if (is_array($ids) && count($ids)) {
            $this->webClient->initialize('product', __CLASS__);

            $payload = [
                'action' => 'delete',
                'entity' => $ids,
            ];

            $this->webClient->pushWebhook($payload);
        }

        return $result;
    }
}
