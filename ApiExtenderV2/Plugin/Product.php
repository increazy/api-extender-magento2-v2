<?php

namespace Increazy\ApiExtenderV2\Plugin;

use Magento\Framework\Api\SearchResultsInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\CatalogRule\Model\ResourceModel\Rule as RuleModel;
use Magento\Framework\Stdlib\DateTime\DateTime as DateTimeMagento;
use Magento\Customer\Model\ResourceModel\Group\Collection as CustomerGroup;
use Magento\Store\Model\StoreManagerInterface;
use Magento\CatalogInventory\Model\Stock\Item;
use Magento\Catalog\Model\Product as ProductModel;
use Magento\Swatches\Helper\Data;

class Product
{
    private $storeManager;
    private $product;
    private $swatchHelper;
    private $stock;
    private $rule;
    private $customerGroup;
    private $dateTime;

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory
     */
    private $categoryCollectionFactory;

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Category\Collection
     */
    private $categoryCollectionCache; 

    /**
     * @var \Magento\Reports\Model\ResourceModel\Product\Sold\CollectionFactory
     */
    private $soldCollectionFactory;

    /**
     * @var array
     */
    private $productSoldQtyCache;

    public function __construct(
        Item $stock,
        RuleModel $rule,
        ProductModel $product,
        Data $swatchHelper,
        StoreManagerInterface $storeManager,
        CustomerGroup $customerGroup,
        DateTimeMagento $dateTime,
        \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $categoryCollectionFactory,
        \Magento\Reports\Model\ResourceModel\Product\Sold\CollectionFactory $soldCollectionFactory
    ) {
        $this->storeManager = $storeManager;
        $this->stock = $stock;
        $this->rule = $rule;
        $this->swatchHelper = $swatchHelper;
        $this->product = $product;
        $this->customerGroup = $customerGroup;
        $this->dateTime = $dateTime;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->soldCollectionFactory = $soldCollectionFactory;
    }
    /**
     * @param \Magento\Catalog\Model\Product[] $items
     */
    protected function _prepareCategoryCache(array $items)
    {
        $ids = [];
        foreach ($items as $product)
        {
            $ids = array_merge($ids, $product->getCategoryIds());
        }

        $ids = array_unique($ids);

        $this->categoryCollectionCache = $this->categoryCollectionFactory->create();

        $this->categoryCollectionCache->addAttributeToFilter('entity_id', ['in' => $ids])->addAttributeToSelect('*');

        return $this;

    }
    public function afterGetList(ProductRepositoryInterface $subject, SearchResultsInterface $searchCriteria)
    {

        $products = [];
        $this->_prepareCategoryCache($searchCriteria->getItems());
        $this->_prepareSoldCache($searchCriteria->getItems());
        
        foreach ($searchCriteria->getItems() as $entity) {
            $extensionAttributes = $entity->getExtensionAttributes();

            $getters = [
                'categories'       => $this->getCategories($entity),
                'store'            => $entity->getStoreId(),
                'meta_title'       => $entity->getMetaTitle(),
                'meta_keywords'    => $entity->getMetaKeyword(),
                'meta_description' => $entity->getMetaDescription(),
                'linkeds'          => $this->getSubProducts($entity),
                'sold'             => $this->getSold($entity),
            ];

            $extensionAttributes->setIncreazy(json_encode([
                'getters'    => $getters,
                'media'      => $this->getMedia($entity),
                'stock'      => $this->getStock($entity),
                'prices'     => $this->getPrices($entity) ?? [],
            ]));

            $entity->setExtensionAttributes($extensionAttributes);
            $products[] = $entity;
        }

        $searchCriteria->setItems($products);
        return $searchCriteria;
    }

    private function getMedia($entity)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

        $result = [];

        foreach($entity->getMediaGalleryImages() as $image){
            $small = $objectManager->create('Magento\Catalog\Helper\Image')
                ->init($entity, 'category_page_grid')
                ->setImageFile($image->getFile())
            ->getUrl();

            $large = $objectManager->create('Magento\Catalog\Helper\Image')
                ->init($entity, 'product_page_image_large')
                ->setImageFile($image->getFile())
            ->getUrl();

            $result[] = array_merge($image->getData(), [
                'small' => $small,
                'large' => $large,
            ]);
        }

        return $result;
    }
    
    /**
     * @param \Magento\Catalog\Model\Product[] $items
     */
    protected function _prepareSoldCache($items)
    {
        $soldCollection = $this->soldCollectionFactory->create();
        $this->productSoldQtyCache = [];
        $ids = [];
        array_walk($items, function ($value) use (&$ids) {
           
            $ids[] = $value->getId();
        });
        $soldCollection
            ->addOrderedQty()
        ->addAttributeToFilter('product_id',["in" => $ids]);
        $soldCollection->getSelect()->columns(
            'order_items.product_id'
        );
        $soldCollection->walk(function($item) {
            $this->productSoldQtyCache[$item->getProductId()] = $item->getOrderedQty();
        });
        
        return $this;
        
    }
    private function getSold($entity)
    {

        if(!isset($this->productSoldQtyCache[$entity->getId()])) return 0;

        return (int)$this->productSoldQtyCache[$entity->getId()];
    }

    private function getCategories($entity)
    {
        
        $result = [];

        foreach($entity->getCategoryIds() as $catId){
            $category = $this->categoryCollectionCache->getItemById($catId);
            $result[] = $category->getData();
        }

        return $result;
    }


    private function getSubProducts($entity)
    {
        return array_values(array_map(function($productId) {
	    return [
		'attribute' => 'id',
	        'value' => $productId
	    ];
        }, $entity->getExtensionAttributes()->getConfigurableProductLinks() ?? []));
    }

    private function getStock($entity)
    {
        $stockItem = $this->stock->load($entity->getId(), 'product_id');

		$stock = $stockItem->getData();
		$stock['salable'] = $stock['qty'];

		if (class_exists('\Magento\InventorySalesAdminUi\Model\GetSalableQuantityDataBySku')) {
			try {
				$objectManager = \Magento\Framework\App\ObjectManager::getInstance();
				$StockState = $objectManager->get('\Magento\InventorySalesAdminUi\Model\GetSalableQuantityDataBySku');
				$qty = $StockState->execute($entity->getSku());

				if (count($qty) > 0) {
					$stock['salable'] = $qty[0]['qty'] ?? $stock['qty'];
				}
			} catch(\Error $e) {}
		}

        return array_merge($stock, [
            'sku'     => $entity->getSku(),
        ]);
    }

    private function getPrices($entity)
    {
        $groups = $this->customerGroup->toOptionArray();
        $websites = $this->storeManager->getWebsites();
        $rules = [];

        foreach ($websites as $websiteID => $websiteConfig) {
            foreach ($groups as $group) {
                // if ($entity->getTypeId() === 'configurable') {
                //     $children = $entity->getTypeInstance()->getUsedProducts($entity);

                //     foreach ($children as $child){
                //         $rules[] = $this->caculateMinPrice([
                //             'website'       => $websiteID,
                //             'group'         => $group['value'],
                //             'sku'           => $child->getSku(),
                //             'product_id'    => $child->getId(),
                //             'special_price' => $child->getSpecialPrice(),
                //             'sale_price'    => $child->getSpecialPrice(),
                //             'price'         => $child->getPrice(),
                //             'special_date'  => $child->getSpecialToDate(),
                //             'rule'         => $this->rule->getRulesFromProduct(
                //                 $this->dateTime->gmtDate(),
                //                 $websiteID,
                //                 $group['value'],
                //                 $child->getId()
                //             )[0] ?? null,
                //         ]);
                //     }
                // } else {
                    $rules[] = $this->caculateMinPrice([
                        'website'       => $websiteID,
                        'group'         => $group['value'],
                        'sku'           => $entity->getSku(),
                        'product_id'    => $entity->getId(),
                        'special_price' => $entity->getSpecialPrice(),
                        'sale_price'    => $entity->getSpecialPrice(),
                        'price'         => $entity->getPrice(),
                        'special_date'  => $entity->getSpecialToDate(),
                        'rule'         => $this->rule->getRulesFromProduct(
                            $this->dateTime->gmtDate(),
                            $websiteID,
                            $group['value'],
                            $entity->getId()
                        )[0] ?? null,
                    ]);
                // }
            }
        }

        return $rules;
    }


    private function caculateMinPrice($price)
    {
        $rule = $price['rule'];
        unset($price['rule']);
        $sDate = $price['special_date'];

        $inSpecialPriceWithoutDate = $sDate == null && $price['special_price'] > 0 && $price['special_date'] != $price['price'];
        $inSpecialPriceWithDate = $sDate != null && strtotime('now') < strtotime($sDate ?? 'now');
        $inSpecialPrice = $inSpecialPriceWithoutDate || $inSpecialPriceWithDate;
        $specialPrice = $inSpecialPrice ? $price['special_price'] : $price['price'];

        // by_percent aplica o percentual
        // by_fixed aplica o bruto
        // to_percent o preço final é igual a esse percentual
        // to_fixed o preço final é igual a esse valor
        if ($rule && !$inSpecialPrice) {
            if ($rule['action_operator'] === 'by_percent') {
                $specialPrice = $specialPrice - ($specialPrice * ($rule['action_amount'] / 100));
            } elseif ($rule['action_operator'] === 'by_fixed') {
                $specialPrice = $specialPrice - $rule['action_amount'];
            } elseif ($rule['action_operator'] === 'to_percent') {
                $specialPrice = $specialPrice * ($rule['action_amount'] / 100);
            } elseif ($rule['action_operator'] === 'to_fixed') {
                $specialPrice = $rule['action_amount'];
            }
        }

        $price['sale_price'] = $specialPrice;

        return $price;
    }
}
