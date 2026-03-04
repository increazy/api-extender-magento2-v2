<?php

namespace Increazy\ApiExtenderV2\Plugin;

use Magento\Framework\Api\SearchResultsInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\CatalogRule\Model\ResourceModel\Rule as RuleModel;
use Magento\Framework\Stdlib\DateTime\DateTime as DateTimeMagento;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
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
    private $localeDate;

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
        \Magento\Reports\Model\ResourceModel\Product\Sold\CollectionFactory $soldCollectionFactory,
        TimezoneInterface $localeDate = null
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
        $this->localeDate = $localeDate
            ?: \Magento\Framework\App\ObjectManager::getInstance()->get(TimezoneInterface::class);
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
                'breadcrumb' => $this->getBreadcrumb($entity) ?? [],
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
            $product = $this->product->load($productId);
            return array_merge($product->getData(), [
                'prices' => $this->getPrices($product),
                'media'  => $product->getMediaGalleryImages()->toArray()['items'],
                'stock'  => $this->getStock($product),
            ]);
        }, $entity->getExtensionAttributes()->getConfigurableProductLinks() ?? []));
    }

    private function getBreadcrumb($entity)
    {
        $evercrumbs = [];

        $categoryCollection = clone $entity->getCategoryCollection();
        $categoryCollection->clear();
        $categoryCollection->addAttributeToSort('level', $categoryCollection::SORT_ORDER_DESC)
            ->addAttributeToFilter('path', [
                'like' => "1/" . $this->storeManager->getStore()->getRootCategoryId() . "/%"]
            );

        $categoryCollection->setPageSize(1);
        $breadcrumbCategories = $categoryCollection->getFirstItem()->getParentCategories();

        foreach ($breadcrumbCategories as $category) {
            $evercrumbs[] = array(
                'label' => $category->getName(),
                'title' => $category->getName(),
                'link'  => $category->getUrl()
            );
        }

        $evercrumbs[] = [
            'label' => $entity->getName(),
            'title' => $entity->getName(),
            'link'  => ''
        ];

        return $evercrumbs;
    }

    private function getStock($entity)
    {
        $stockItem = $this->stock->load($entity->getId(), 'product_id');

        return array_merge($stockItem->getData(), [
            'sku'     => $entity->getSku(),
            'salable' => $entity->isSalable(),
        ]);
    }

    private function getPrices($entity)
    {
        $groups = $this->customerGroup->toOptionArray();
        $websites = $this->storeManager->getWebsites();
        $rules = [];

        foreach ($websites as $websiteID => $websiteConfig) {
            $defaultStore = $websiteConfig->getDefaultStore();
            $date = $this->localeDate->scopeDate($defaultStore ? $defaultStore->getId() : null);

            foreach ($groups as $group) {
                $ruleData = $this->getRulePriceData(
                    $date,
                    $websiteID,
                    $group['value'],
                    $entity->getId()
                );

                $rules[] = $this->caculateMinPrice([
                    'website'       => $websiteID,
                    'group'         => $group['value'],
                    'sku'           => $entity->getSku(),
                    'product_id'    => $entity->getId(),
                    'special_price' => $entity->getSpecialPrice(),
                    'sale_price'    => $entity->getSpecialPrice(),
                    'price'         => $entity->getPrice(),
                    'special_date'  => $entity->getSpecialToDate(),
                    'rule_price'    => $ruleData ? $ruleData['rule_price'] : false,
                    'rule_end_date' => $ruleData && isset($ruleData['earliest_end_date']) ? $ruleData['earliest_end_date'] : null,
                ]);
            }
        }

        return $rules;
    }

    private function getRulePriceData($date, $websiteId, $customerGroupId, $productId)
    {
        $connection = $this->rule->getConnection();
        $select = $connection->select()
            ->from($this->rule->getTable('catalogrule_product_price'), ['rule_price', 'earliest_end_date'])
            ->where('rule_date = ?', $date->format('Y-m-d'))
            ->where('website_id = ?', $websiteId)
            ->where('customer_group_id = ?', $customerGroupId)
            ->where('product_id = ?', $productId);

        return $connection->fetchRow($select);
    }

    private function caculateMinPrice($price)
    {
        $rulePrice = isset($price['rule_price']) ? $price['rule_price'] : false;
        $ruleEndDate = isset($price['rule_end_date']) ? $price['rule_end_date'] : null;
        unset($price['rule_price'], $price['rule_end_date']);

        $sDate = $price['special_date'];

        $inSpecialPriceWithoutDate = $sDate == null
            && $price['special_price'] > 0
            && $price['special_price'] != $price['price'];

        $inSpecialPriceWithDate = $sDate != null
            && strtotime('now') <= strtotime($sDate);

        $inSpecialPrice = $inSpecialPriceWithoutDate || $inSpecialPriceWithDate;

        $specialPrice = $inSpecialPrice ? (float) $price['special_price'] : (float) $price['price'];

        // Aplica o preço da regra de catálogo se for menor (mesmo comportamento do Magento)
        if ($rulePrice !== false && $rulePrice !== null) {
            $rulePriceFloat = (float) $rulePrice;
            if ($rulePriceFloat < $specialPrice) {
                $price['special_price'] = $rulePriceFloat;
                // Subtrai 1 dia para retornar a data exata do admin (earliest_end_date é o primeiro dia inativo)
                if ($ruleEndDate) {
                    $price['special_date'] = date('Y-m-d', strtotime($ruleEndDate . ' -1 day'));
                } else {
                    $price['special_date'] = null;
                }
            }
        }

        $price['sale_price'] = $specialPrice;

        return $price;
    }
}