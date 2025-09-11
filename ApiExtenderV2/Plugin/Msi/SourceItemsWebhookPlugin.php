<?php
declare(strict_types=1);

namespace Increazy\ApiExtenderV2\Plugin\Msi;

use Closure;
use Increazy\ApiExtenderV2\Model\WebClientInterface;
use Magento\InventoryApi\Api\SourceItemsSaveInterface;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;

class SourceItemsWebhookPlugin
{
    private WebClientInterface $webClient;
    private ResourceConnection $resource;
    private LoggerInterface $logger;

    public function __construct(
        WebClientInterface $webClient,
        ResourceConnection $resource,
        LoggerInterface $logger
    ) {
        $this->webClient = $webClient;
        $this->resource  = $resource;
        $this->logger    = $logger;
    }

    /**
     * aroundExecute: salva MSI, atualiza updated_at por SQL e notifica 1x por SKU.
     *
     * @param SourceItemsSaveInterface $subject
     * @param Closure $proceed
     * @param SourceItemInterface[] $sourceItems
     * @return void
     */
    public function aroundExecute(
        SourceItemsSaveInterface $subject,
        Closure $proceed,
        array $sourceItems
    ) {
        $proceed($sourceItems); // salva primeiro

        // SKUs únicos
        $uniqueSkus = [];
        foreach ($sourceItems as $item) {
            $sku = (string)$item->getSku();
            if ($sku !== '') $uniqueSkus[$sku] = true;
        }
        if (!$uniqueSkus) return;

        // Mapear sku => entity_id e atualizar updated_at em lote (SQL puro)
        $conn  = $this->resource->getConnection();
        $table = $this->resource->getTableName('catalog_product_entity');

        try {
            $select = $conn->select()
                ->from($table, ['sku','entity_id'])
                ->where('sku IN (?)', array_keys($uniqueSkus));
            $skuToId = (array)$conn->fetchPairs($select);

            if ($skuToId) {
                $ids = array_values($skuToId);
                $nowUtc = gmdate('Y-m-d H:i:s');

                $conn->update($table, ['updated_at' => $nowUtc], ['entity_id IN (?)' => $ids]);

                // Notificar 1x por SKU
                foreach ($skuToId as $sku => $productId) {
                    if ((int)$productId > 0) {
                        $this->webClient->initialize('product', __CLASS__);
                        $this->webClient->pushWebhook([
                            'action' => 'save',
                            'entity' => (int)$productId,
                        ]);
                    }
                }
            }
        } catch (\Throwable $e) {
            $this->logger->error('Falha ao atualizar updated_at / notificar (MSI)', [
                'skus' => array_keys($uniqueSkus),
                'error' => $e->getMessage(),
            ]);
        }
    }
}