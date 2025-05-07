<?php

namespace Increazy\ApiExtenderV2\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\CatalogRule\Model\RuleFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Increazy\ApiExtenderV2\Model\WebClientInterface;

class CatalogRuleSave implements ObserverInterface
{
    /**
     * @var WebClientInterface
     */
    private $webClient;

    /**
     * @var RuleFactory
     */
    private $ruleFactory;

    /**
     * @var CollectionFactory
     */
    private $productCollectionFactory;

    public function __construct(
        WebClientInterface $webClient,
        RuleFactory $ruleFactory,
        CollectionFactory $productCollectionFactory
    ) {
        $this->webClient = $webClient;
        $this->ruleFactory = $ruleFactory;
        $this->productCollectionFactory = $productCollectionFactory;
    }

    public function execute(Observer $observer)
    {
        $rule = $observer->getEvent()->getRule();

        // Recarrega a regra completa para garantir consistência
        $ruleModel = $this->ruleFactory->create()->load($rule->getId());

        // Obter produtos impactados
        $productCollection = $this->productCollectionFactory->create();
        $productIds = [];
 
        foreach ($productCollection as $product) {
            if ($ruleModel->validate($product)) {
                $productIds[] = $product->getId();
            }
        }

        // Envia webhook no padrão esperado
        $this->webClient->initialize('product', __CLASS__);

        $payload = [
            'action' => 'save',
            'entity' => $productIds,
        ];

        $this->webClient->pushWebhook($payload);
    }
}
