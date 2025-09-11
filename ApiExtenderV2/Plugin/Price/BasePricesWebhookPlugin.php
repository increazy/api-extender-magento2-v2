<?php
declare(strict_types=1);

namespace Increazy\ApiExtenderV2\Plugin\Price;

use Closure;
use Increazy\ApiExtenderV2\Model\WebClientInterface;
use Magento\Catalog\Api\BasePriceStorageInterface;
use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;

class BasePricesWebhookPlugin
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
     * Salva preços, atualiza updated_at por SQL e notifica 1x por SKU.
     *
     * @param BasePriceStorageInterface $subject
     * @param Closure $proceed
     * @param array $prices  // array de BasePriceInterface ou arrays compatíveis
     * @return mixed
     */
    public function aroundUpdate(
        BasePriceStorageInterface $subject,
        Closure $proceed,
        array $prices
    ) {
        $result = $proceed($prices); // salva primeiro

        // SKUs únicos
        $uniqueSkus = [];
        foreach ($prices as $p) {
            $sku = is_object($p) && method_exists($p, 'getSku') ? (string)$p->getSku() : (string)($p['sku'] ?? '');
            if ($sku !== '') $uniqueSkus[$sku] = true;
        }
        if (!$uniqueSkus) return $result;

        // Mapear sku => entity_id e atualizar updated_at em lote (SQL puro)
        $conn  = $this->resource->getConnection();
        $table = $this->resource->getTableName('catalog_product_entity');

        try {
            // sku => entity_id
            $select = $conn->select()
                ->from($table, ['sku','entity_id'])
                ->where('sku IN (?)', array_keys($uniqueSkus));
            $skuToId = (array)$conn->fetchPairs($select);

            if ($skuToId) {
                $ids = array_values($skuToId);
                $nowUtc = gmdate('Y-m-d H:i:s');

                // UPDATE em lote
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
            $this->logger->error('Falha ao atualizar updated_at / notificar (base-prices)', [
                'skus' => array_keys($uniqueSkus),
                'error' => $e->getMessage(),
            ]);
        }

        return $result;
    }
}