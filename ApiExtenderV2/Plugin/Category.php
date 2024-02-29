<?php
namespace Increazy\ApiExtenderV2\Plugin;

use Magento\Catalog\Api\Data\CategoryExtensionFactory;
use Magento\Catalog\Api\Data\CategoryExtensionInterface;
use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Catalog\Api\CategoryRepositoryInterface;

class Category
{

    protected $extensionFactory;
    protected $objectManager;


    /**
     * @param \Magento\Catalog\Api\Data\CategoryExtensionFactory
     * $extensionFactory
     */
    public function __construct(\Magento\Catalog\Api\Data\CategoryExtensionFactory $extensionFactory)
    {
        $this->extensionFactory = $extensionFactory;
        $this->objectManager = \Magento\Framework\App\ObjectManager::getInstance();
    }

    /**
     * @param CategoryRepositoryInterface $subject
     * @param CaetgoryInterface $category
     *
     * @return CaetgoryInterface
     */
    public function afterGet(\Magento\Catalog\Api\CategoryRepositoryInterface $subject, \Magento\Catalog\Api\Data\CategoryInterface $category)
    {
        $extensionAttributes = $category->getExtensionAttributes();
        $extensionAttributes = $extensionAttributes ? $extensionAttributes : $this->extensionFactory->create();

        $extensionAttributes->setIncreazy(json_encode([
          'image'         => $this->getImage($category),
          'products'      => $this->getSequenceProducts($category),
          'subcategories' => $this->getSubCategories($category),
          'stores'        => $category->getStoreIds(),
        ]));
        $category->setExtensionAttributes($extensionAttributes);

        return $category;
    }

    protected function getSubCategories($category)
    {
        $categoryFull = $this->objectManager
            ->create('Magento\Catalog\Model\Category')
        ->load($category->getId());
        $subcategories = [];
        $children = $categoryFull->getChildrenCategories();

        foreach ($children as $child) {
            $subcategories[] = $child->getId();
        }

        return $subcategories;
    }

    protected function getImage($category) {
      if ($category->getImageUrl()) {
        $storeManager = $this->objectManager->get('Magento\Store\Model\StoreManagerInterface');
        $currentStore = $storeManager->getStore();
        $mediaUrl = $currentStore->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);

        return $mediaUrl . $category->getImageUrl();
      }

      return null;
    }

    protected function getSequenceProducts($category)
    {
        $products = [];
        $productCollection = $this->objectManager->create('Magento\Catalog\Model\ResourceModel\Product\Collection')
            ->addAttributeToSelect(['cat_index_position', 'sku'])
            ->addAttributeToFilter('status', 1)
            ->addCategoryFilter($category)
            ->groupByAttribute('sku');

        foreach ($productCollection as $product)
        {
            $products[] = [
                'sku' => $product->getSku(),
                'position' => $product->getCatIndexPosition(),
              ];
        }

        return $products;
    }
}
