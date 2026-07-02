<?php
declare(strict_types=1);

namespace Increazy\ApiExtenderV2\Plugin\Price;

use Closure;
use Increazy\ApiExtenderV2\Model\WebClientInterface;
use Magento\Catalog\Api\SpecialPriceStorageInterface;
use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;

class SpecialPricesWebhookPlugin
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
     * Salva special prices, atualiza updated_at por SQL e notifica 1x por SKU.
     */
    public function aroundUpdate(
        SpecialPriceStorageInterface $subject,
        Closure $proceed,
        array $prices
    ) {
        $result = $proceed($prices);

        $this->syncAndNotify($prices, 'save');

        return $result;
    }

    /**
     * Remove special prices, atualiza updated_at por SQL e notifica 1x por SKU.
     */
    public function aroundDelete(
        SpecialPriceStorageInterface $subject,
        Closure $proceed,
        array $prices
    ) {
        $result = $proceed($prices);

        $this->syncAndNotify($prices, 'save');

        return $result;
    }

    /**
     * Colhe os SKUs unicos do payload, atualiza catalog_product_entity.updated_at
     * em lote e envia 1 webhook por produto.
     */
    private function syncAndNotify(array $prices, string $action): void
    {
        $uniqueSkus = [];
        foreach ($prices as $p) {
            $sku = is_object($p) && method_exists($p, 'getSku') ? (string)$p->getSku() : (string)($p['sku'] ?? '');
            if ($sku !== '') {
                $uniqueSkus[$sku] = true;
            }
        }
        if (!$uniqueSkus) {
            return;
        }

        $conn  = $this->resource->getConnection();
        $table = $this->resource->getTableName('catalog_product_entity');

        try {
            $select = $conn->select()
                ->from($table, ['sku', 'entity_id'])
                ->where('sku IN (?)', array_keys($uniqueSkus));
            $skuToId = (array)$conn->fetchPairs($select);

            if ($skuToId) {
                $ids = array_values($skuToId);
                $nowUtc = gmdate('Y-m-d H:i:s');

                $conn->update($table, ['updated_at' => $nowUtc], ['entity_id IN (?)' => $ids]);

                foreach ($skuToId as $sku => $productId) {
                    if ((int)$productId > 0) {
                        $this->webClient->initialize('product', __CLASS__);
                        $this->webClient->pushWebhook([
                            'action' => $action,
                            'entity' => (int)$productId,
                        ]);
                    }
                }
            }
        } catch (\Throwable $e) {
            $this->logger->error('Falha ao atualizar updated_at / notificar (special-prices)', [
                'skus'   => array_keys($uniqueSkus),
                'action' => $action,
                'error'  => $e->getMessage(),
            ]);
        }
    }
}
