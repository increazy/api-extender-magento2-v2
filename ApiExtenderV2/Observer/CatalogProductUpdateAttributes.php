<?php
namespace Increazy\ApiExtenderV2\Observer;

use Increazy\ApiExtenderV2\Model\WebClientInterface;
use Magento\Ui\Component\MassAction\Filter;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\Exception\LocalizedException;

class CatalogProductUpdateAttributes implements \Magento\Framework\Event\ObserverInterface
{
    protected $catalogProductEditActionAttributeHelper;

    /**
     * @var WebClientInterface
     */
    protected $webClient;

    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;
    
    /**
     * MassActions filter
     *
     * @var Filter
     */
    protected $filter;

    public function __construct(
        WebClientInterface $webClient,
        Filter $filter,
        CollectionFactory $collectionFactory
    ) {
        $this->webClient = $webClient;
        $this->filter = $filter;
        $this->collectionFactory = $collectionFactory;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        try {
            
            $collection = $this->filter->getCollection($this->collectionFactory->create());
            $productIds = $collection->getAllIds();
            if (!isset($productIds)) return;
    
            $this->webClient->initialize('product', __CLASS__);
    
            $this->webClient->pushWebhook([
                'action' => 'save',
                'entity' => $productIds,
            ]);
        }

        catch (LocalizedException $err)
        {
            
        }
    }
}