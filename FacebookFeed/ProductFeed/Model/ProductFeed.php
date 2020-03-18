<?php
namespace FacebookFeed\ProductFeed\Model;

use FacebookFeed\ProductFeed\Api\ProductFeedInterface;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Exception\NoSuchEntityException;

class ProductFeed implements ProductFeedInterface
{
    /**
     * @param $productcus
     * @return array Custom Attributes of single Product.
     * @api
     */

    public function getCustomAttribute($productcus)
    {
        $objectManager = ObjectManager::getInstance();
        $productFactory = $objectManager->get('\Magento\Catalog\Model\ResourceModel\ProductFactory');
        $custom=[];
        foreach ($productcus->getCustomAttributes() as $oneattributes) {
            $attri=[];
            if ($oneattributes->getAttributeCode()=='size' || $oneattributes->getAttributeCode()=='color' || $oneattributes->getAttributeCode()=='shades') {
                $poductReource=$productFactory->create();
                $attribute = $poductReource->getAttribute($oneattributes->getAttributeCode());
                if ($attribute->usesSource()) {
                    $option_Text = $attribute->getSource()->getOptionText($oneattributes->getValue());
                    $attri['attribute_code']=$oneattributes->getAttributeCode();
                    $attri['value']=$option_Text;
                }
            } else {
                $attri['attribute_code']=$oneattributes->getAttributeCode();
                $attri['value']=$oneattributes->getValue();
            }
            $custom[]=$attri;
        }
        return $custom;
    }

    /**
     * @param $images
     * @return array All images of single Product.
     * @api
     */

    public function getImages($images)
    {
        $allimages=[];
        foreach ($images as $child) {
            $allimages[]=$child->getUrl();
        }
        return $allimages;
    }

    /**
     * @param $productcus
     * @return string Product in-stock or out-of-stock.
     * @api
     */

    public function getAvailability($productcus)
    {
        if ($productcus->isSaleable()==true) {
            return 'in stock';
        } else {
            return 'out of stock';
        }
    }

    /**
     * @param $catids
     * @return array Category names of single Product.
     * @api
     */

    public function getCategoryNames($catids)
    {
        $categoryRepository = ObjectManager::getInstance()->get(CategoryRepositoryInterface::class);
        $catname=[];
        foreach ($catids as $categoryId) {
            $singlecat = $categoryRepository->get($categoryId);
            $catname[]=$singlecat->getName();
        }
        return $catname;
    }
    
     /**
     * Returns Parent Product 
     * @return array Parent Product Details
     * @api
     */

    public function getParentProduct($parentId)
    {
        $parentPrd=[];
        if(isset($parentId[0])){
            $objectManager =  ObjectManager::getInstance();
            $productRepository = $objectManager->get('\Magento\Catalog\Model\ProductRepository');
            $parentProduct = $productRepository->getById($parentId[0]);
            $configImages = $parentProduct->getMediaGalleryImages();
            $url=$parentProduct->getProductUrl();
            $urlarray=explode('/', $url);
            $slugval=explode('.', end($urlarray));
            $parentPrd['parent_id']=$parentId[0];
            $parentPrd['parent_name']= $parentProduct->getName();
            $parentPrd['parent_images']=$this->getImages($configImages);
            $parentPrd['parent_slug']=current($slugval);
            $parentPrd['parent_customAttributes']=$this->getCustomAttribute($parentProduct);
        }
        return $parentPrd;
    } 

    /**
     * Returns Product List
     * @return array List of all product info for Product Feed.
     * @throws NoSuchEntityException
     * @api
     */

    public function name()
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $productCollection = $objectManager->create('Magento\Catalog\Model\ResourceModel\Product\CollectionFactory');
        $collection = $productCollection->create()->addAttributeToSelect('*')->addAttributeToFilter('type_id', 'simple')->addAttributeToFilter('status', 1)->load();
        //echo $collection->count();
        //echo $products->getSelect();
        //exit;
        $links = [];
        $objectManager =  ObjectManager::getInstance();
        $storeManager = $objectManager->get('\Magento\Store\Model\StoreManagerInterface'); 
        $productRepository = $objectManager->get('\Magento\Catalog\Model\ProductRepository');
        /** @var Product $product */
        foreach ($collection->getItems() as $product) {
            $productcus = $productRepository->getById($product->getId());
            $images = $productcus->getMediaGalleryImages();
            $url= $productcus->getProductUrl();
            $urlarray=explode('/', $url);
            $slugval=explode('.', end($urlarray));
            $catids = $productcus->getCategoryIds();
            $link=[];
            $link['sku']=$productcus->getSku();
            $link['categories']=$productcus->getCategoryIds();
            $link['categories_names']=$this->getCategoryNames($catids);
            $link['regular_price']=number_format($productcus->getPriceInfo()->getPrice('regular_price')->getAmount()->getValue(), 2, '.', ',');
            $link['final_price']=number_format($productcus->getPriceInfo()->getPrice('final_price')->getAmount()->getValue(), 2, '.', ',');
            $link['currency_code']=$storeManager->getStore()->getCurrentCurrencyCode(); 
            $link['availability']=$this->getAvailability($productcus);
            $link['createdAt']=$productcus->getCreatedAt();
            $link['visibility']=$productcus->getAttributeText('visibility')->getText();
            if ($productcus->getAttributeText('visibility')->getText() == "Not Visible Individually") {
                $parentId = $objectManager->create('Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable')->getParentIdsByChild($product->getId());
                $link['parent_product']=$this->getParentProduct($parentId);
            }

            $link['id']=$product->getId();
            $link['name']= $productcus->getName();
            $link['images']=$this->getImages($images);
            $link['slug']=current($slugval);
            $link['customAttributes']=$this->getCustomAttribute($productcus);
            $links[] = $link;
        }
        return $links;
    }
}
