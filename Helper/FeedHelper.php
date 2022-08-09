<?php
/**
 * The Retargeting Magento 2 extension implements the required tagging for Retargeting's
 * functions in Magento 2 based web-shops.
 *
 * @category    Retargeting
 * @package     Retargeting_Tracking
 * @author      Retargeting Team <info@retargeting.biz>
 * @copyright   Retargeting (https://retargeting.biz)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Retargeting\Tracker\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Catalog\Api\ProductRepositoryInterface;

use Magento\Store\Model\Store;

use Retargeting\Tracker\Helper\PriceHelper;
use Retargeting\Tracker\Helper\StockHelper;
use Retargeting\Tracker\Helper\Data;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;

use Magento\ConfigurableProduct\Model\Product\Type\Configurable as ConfigurableType;

use Laminas\Db\Sql\Ddl\Column\Integer;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\Website;
/**
 * Class Data
 * @package Retargeting\Tracker\Helper
 */
class FeedHelper extends AbstractHelper
{
    
    public function __construct(
        Context $context,
        FileFactory $fileFactory,
        Filesystem $filesystem,
        Data $_retargetingData,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        StockHelper $retargetingStockHelper,
        PriceHelper $priceHelper,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        ProductRepositoryInterface $productRepository
    ) {
        parent::__construct($context);
        $this->fileFactory = $fileFactory;

        $this->directory = $filesystem->getDirectoryWrite(DirectoryList::PUB);

        $this->_storeManager = $storeManager; 

        $this->_retargetingData = $_retargetingData;

        $this->productRepository = $productRepository;
        
        $this->stockHelper = $retargetingStockHelper;

        $this->priceHelper = $priceHelper;

        $this->searchCriteriaBuilder = $searchCriteriaBuilder;

        $this->cronActive = $_retargetingData->getCfg(\Retargeting\Tracker\Helper\Data::RETARGETING_CRON_FEED, 0);
        $this->defStock = $_retargetingData->getCfg(\Retargeting\Tracker\Helper\Data::RETARGETING_DEFAULT_STOCK, 0);
    }

    private static $ids = [];
    private $defStock = 0;
    public $cronActive = 0;

    public function cronFeed() {

        $this->Store = explode(',',
            $this->_retargetingData->getCfg(\Retargeting\Tracker\Helper\Data::RETARGETING_STORE_SELECT, "1")
        );

        foreach ($this->Store as $storeID) {
           $last = $this->generateFeed(null, 1, 100, false, false, $storeID);
        }

        return $last;
    }

    public function staticFeed() {
        
        return $this->generateFeed(null, 1, 100, true, true);
    }

    public function genFeed() {
        
        return $this->generateFeed();
    }

    public function generateFeed($file = null, $currentPage = 1, $size = 100, $notCron = true, $static = false, $storeID = null) {
        
        if ($storeID === null) {
            $storeID = $this->_storeManager->getStore()->getId();
        }
        if ($file === null) {
            $file = [
                'name' => 'retargeting.'. $storeID,
                'rname' => 'retargeting',
                'tmpName' => 'retargeting.'.time()
            ];
        }

        $status = ['status'=>'success','message'=>null, 'file' => $file['name'].'.csv', 'generated' => false];

        $content = [];
        $content['type'] = 'filename'; // must keep filename
        $content['value'] = $file['name'].'.csv';
        $content['rm'] = '0'; //remove csv from var folder

        if ($static && $this->directory->isFile($file['name'].'.csv')) {

            return $this->fileFactory->create($file['rname'].'.csv', $content, DirectoryList::PUB);
        }

        $status['generated'] = true;

        $stream = $this->directory->openFile($file['tmpName'].'.csv', 'w+');
        $stream->lock();

        $columns = [
            'product id',
            'product name',
            'product url',
            'image url',
            'stock',
            'price',
            'sale price',
            'brand',
            'category',
            'extra data'
        ];

        $stream->writeCsv($columns);

        $productLoopExit = false;
        $lastProductId = 0;

        $store = $this->_storeManager->getStore($storeID);


        while (!$productLoopExit) {
            $products = $this->getProducts($currentPage, $size, $storeID);
            $currentPage++;
            if (!count($products)
                || (array_values(array_slice($products, -1))[0]->getId() == $lastProductId)
            ) {
                $productLoopExit = true;
            } else {
                foreach ($products as $product) {
                    if (in_array($product->getId(), self::$ids)) {
                        continue;
                    } else {
                        self::$ids[$product->getId()] = $product->getId();
                        $productType = $product->getTypeInstance();
                        $bundledItemIds = $productType->getChildrenIds($product->getId(), $required = true);

                        $stock = $this->stockHelper->getQuantity($product, $store);
                        
                        $stock = $stock < 0 ? $this->defStock : $stock;
                        $price = $this->priceHelper->getFullPrice($product);

                        if (empty($price) && $stock === 0) {
                            continue;
                        }

                        /** @noinspection PhpParamsInspection */
                        $stream->writeCsv([
                            'product id' => $product->getId(),
                            'product name' => $product->getName(),
                            'product url' => $this->productURL($product, $notCron),
                            'image url' => $product->getMediaConfig()->getMediaUrl($product->getImage()),
                            'stock' => $stock,
                            'price' => $price,
                            'sale price' => $this->priceHelper->getProductPrice($product),
                            'brand' => '',
                            'category' => $this->_retargetingData->getProductCategory($product->getCategoryIds()),
                            'extra data' => str_replace(['\"'], ["'"],
                                json_encode($this->getExtraDataProduct($bundledItemIds, $store, $product->getId()), JSON_UNESCAPED_UNICODE)
                            )
                        ]);
                    }
                }

                $lastProductId = array_values(array_slice($products, -1))[0]->getId();
            }
        }

        try {
            $this->directory->renameFile($file['tmpName'].'.csv', $file['name'] .'.csv');
        } catch (\Exception $e) {

            $status['status'] = 'readProblem';
            $status['message'] = $e->getMessage();
            return $status;
        }
        if ( $notCron ) {
            return $this->fileFactory->create($file['rname'].'.csv', $content, DirectoryList::PUB);
        }
        return $status;
    }

    private function productURL($product, $notCron = true) {

        $url = $this->fixUrl($product->getProductUrl(false));

        if (!$notCron) {
            $url = preg_replace('/(&|\?)'.preg_quote('___store').'=[^&]*$/', '', $url);
            $url = preg_replace('/(&|\?)'.preg_quote('___store').'=[^&]*&/', '$1', $url);
        }

        return $url;
    }

    function fixUrl($url) {

        $url = str_replace("&amp;", "&", $url);
    
        $new_URL = explode("?", $url, 2);
        $newURL = explode("/",$new_URL[0]);
    
        $checkHttp = !empty(array_intersect(["https:","http:"], $newURL));
    
        foreach ($newURL as $k=>$v ){
            if (!$checkHttp || $checkHttp && $k > 2) {
                $newURL[$k] = rawurlencode($v);
            }
        }
    
        if (isset($new_URL[1])) {
            $new_URL[0] = implode("/",$newURL);
            $new_URL[1] = str_replace("&amp;","&",$new_URL[1]);
            return implode("?", $new_URL);
        } else {
            return implode("/",$newURL);
        }
    
        return $url;
    }

    /**
     * @param array $productIds
     * @param Store $store
     * @param Integer $productId
     * @return array|null
     * @throws NoSuchEntityException
     */
    protected function getExtraDataProduct(array $productIds, Store $store, $productId) {

        try {
            $website = $store->getWebsite();
        } catch (NoSuchEntityException $e) {
            return null;
        }
        if ($website instanceof Website === false) {
            return null;
        }

        $stockItems = $this->stockHelper->getStockStatuses($productIds, $website);
        $parentProd = $this->productRepository->getById($productId);

        $extraData = [];
        $extraData['margin'] = null;
        $extraData['categories'] = $this->_retargetingData->getProductCategoryNamesById($parentProd->getCategoryIds());
        $extraData['media_gallery'][] = $parentProd->getMediaConfig()->getMediaUrl($parentProd->getImage());
        $extraData['in_supplier_stock'] = null;

        foreach ($stockItems as $productId => $product) {

            $productCollection = $this->productRepository->getById($product->getId());

            $stock = $this->stockHelper->getQuantity($productCollection, $store);
            $price = $this->priceHelper->getFullPrice($productCollection);

            if (empty($price) && $stock === 0) {
                continue;
            }

            $extraData['media_gallery'][] = $this->_retargetingData->getMediaGallery($productCollection);
            $extraData['variations'][] = [
                'code' => $productCollection->getId(),
                'price' => $price,
                'sale_price' => $this->priceHelper->getProductPrice($productCollection),
                'stock' => $stock,
                'margin' => null,
                'in_supplier_stock' => null
            ];


        }

        $extraData['media_gallery'] = $this->_retargetingData->mediaGalleryTransform($extraData['media_gallery']);

        if (!isset($extraData['variations'])) {
            $extraData['variations'] = null;
        }

        return $extraData;

    }

    /**
     * @var SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;

    public function getProducts($page = 1, $pageSize = 100, $StoreID = 1)
    {
        $this->searchCriteriaBuilder->addFilter(
            'type_id',
            [
                Product\Type::TYPE_SIMPLE,
                Product\Type::TYPE_VIRTUAL,
                ConfigurableType::TYPE_CODE
            ],
            'in'
        );

        $this->searchCriteriaBuilder->addFilter(
            'status',
            Status::STATUS_ENABLED
        );

        $this->searchCriteriaBuilder->addFilter('store_id', $StoreID, 'eq');

        $this->searchCriteriaBuilder->addFilter(
            'visibility',
            Visibility::VISIBILITY_BOTH
        );

        $this->searchCriteriaBuilder->setCurrentPage($page);
        $this->searchCriteriaBuilder->setPageSize($pageSize);

        $searchCriteria = $this->searchCriteriaBuilder->create();

        return $this->productRepository->getList($searchCriteria)->getItems();
    }
}
