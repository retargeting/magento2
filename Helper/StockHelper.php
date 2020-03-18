<?php


namespace Retargeting\Tracker\Helper;

use Magento\Bundle\Model\Product\Type as Bundled;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Type as ProductType;
use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\GroupedProduct\Model\Product\Type\Grouped;
use Magento\Store\Model\Store;
use Magento\Store\Model\Website;

/**
 * Class StockHelper
 * @package Retargeting\Tracker\Helper
 */
class StockHelper extends AbstractHelper
{

    private $stockProvider;

    /**
     * StockHelper constructor.
     * @param Context $context
     * @param StockRegistry $stockProvider
     */
    public function __construct(
        Context $context,
        StockRegistry $stockProvider
    )
    {
        parent::__construct($context);
        $this->stockProvider = $stockProvider;
    }

    /**
     * @param Product $product
     * @param Store $store
     * @return int|mixed
     */
    public function getQuantity(Product $product, Store $store)
    {
        $qty = 0;
        try {
            $website = $store->getWebsite();
        } catch (NoSuchEntityException $e) {
            return 0;
        }
        if ($website instanceof Website === false) {
            return 0;
        }
        switch ($product->getTypeId()) {
            case ProductType::TYPE_BUNDLE:
                /** @var Bundled $productType */
                $productType = $product->getTypeInstance();
                $bundledItemIds = $productType->getChildrenIds($product->getId(), $required = true);
                $productIds = [];
                foreach ($bundledItemIds as $variants) {
                    if (is_array($variants) && count($variants) > 0) { // @codingStandardsIgnoreLine
                        foreach ($variants as $productId) {
                            $productIds[] = $productId;
                        }
                    }
                }
                $qty = $this->getMinQty($productIds, $website);
                break;
            case Grouped::TYPE_CODE:
                $productType = $product->getTypeInstance();
                if ($productType instanceof Grouped) {
                    $productIds = $productType->getAssociatedProductIds($product);
                    $qty = $this->getMinQty($productIds, $website);
                }
                break;
            case Configurable::TYPE_CODE:
                $productType = $product->getTypeInstance();
                if ($productType instanceof Configurable) {
                    $productIds = $productType->getChildrenIds($product->getId());
                    if (isset($productIds[0]) && is_array($productIds[0])) {
                        $productIds = $productIds[0];
                    }
                    $qty = $this->getQtySum($productIds, $website);
                }
                break;
            default:
                $qty += $this->getAvailableQuantity($product, $website);
                break;
        }

        return (int)$qty;
    }

    /**
     * @param array $productIds
     * @param Website $website
     * @return int|mixed
     */
    private function getMinQty(array $productIds, Website $website)
    {
        $quantities = $this->getQuantitiesByIds($productIds, $website);
        $minQty = 0;
        if (!empty($quantities)) {
            rsort($quantities, SORT_NUMERIC);
            $minQty = array_pop($quantities);
        }
        return $minQty;
    }

    /**
     * @param array $productIds
     * @param Website $website
     * @return array
     */
    public function getQuantitiesByIds(array $productIds, Website $website)
    {
        $quantities = [];
        $stockItems = $this->getStockStatuses($productIds, $website);
        /* @var Product $product */
        foreach ($stockItems as $stockItem) {
            $quantities[$stockItem->getProductId()] = $stockItem->getQty();
        }
        return $quantities;
    }

    /**
     * @param array $ids
     * @param Website $website
     * @return array
     */
    private function getStockStatuses(
        array $ids,
        /** @noinspection PhpUnusedParameterInspection */
        Website $website
    ): array
    {
        return $this->stockProvider->getStockStatuses(
            $ids,
            StockRegistry::DEFAULT_STOCK_SCOPE
        )->getItems();
    }

    /**
     * @param $productIds
     * @param Website $website
     * @return int|mixed
     */
    private function getQtySum($productIds, Website $website)
    {
        $qty = 0;
        $quantities = $this->getQuantitiesByIds($productIds, $website);
        foreach ($quantities as $quantity) {
            $qty += $quantity;
        }
        return $qty;
    }

    /**
     * @param Product $product
     * @param Website $website
     * @return int
     */
    public function getAvailableQuantity(
        Product $product,
        Website $website
    )
    {
        return (int)$this->getStockItem($product)->getQty();
    }

    /**
     * @param Product $product
     * @return StockItemInterface
     */
    private function getStockItem(Product $product)
    {
        return $this->stockProvider->getStockItem(
            $product->getId(),
            StockRegistry::DEFAULT_STOCK_SCOPE
        );
    }

    /**
     * @param Product $product
     * @param Store $store
     * @return bool
     */
    public function isInStock(Product $product, Store $store)
    {
        try {
            return (bool)$this->stockProvider->isInStock(
                $product,
                $store->getWebsite()
            );
        } catch (NoSuchEntityException $e) {
            return false;
        }
    }
}