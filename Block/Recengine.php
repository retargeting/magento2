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

namespace Retargeting\Tracker\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\App\Request\Http;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\Serialize\SerializerInterface;

/**
 * Class Tracker
 * @package Retargeting\Tracker\Block
 */
class Recengine extends Template
{
    private static $_d = null;
    private static $_req = null;
    // private static $store = null;
    private static $cfg = null;
    private static $serializer = null;

    public function __construct(Context $context, Http $request, SerializerInterface $serializer, array $data = []) {
        self::$_req = $request;
        self::$_d = $data;
        // self::$store = $storeManager->getStore()->getId();
        self::$cfg =\Magento\Framework\App\ObjectManager::getInstance()->get(\Magento\Framework\App\Config\ScopeConfigInterface::class);
        self::$serializer = $serializer;

        parent::__construct($context);
    }
    private static $rec_engine = array(
        "cms_index_index" => "home_page",
        "checkout_onepage_success" => "thank_you_page", /* Importanta Ordinea checkout_onepage_success */
        "checkout_onepage_index" => "shopping_cart",
        "checkout_cart_index" => "shopping_cart",
        "checkout_index_index" => "shopping_cart",
        "catalog_category_view" => "category_page",
        "catalog_product_view" => "product_page",
        "catalogsearch_result_index" => "search_page",
        "cms_index_noRoute" => "page_404",
        "cms_noroute_index" => "page_404",
        // "catalog_category_view" => "category_page"
    );

    public static function status() {
        return (bool) self::cfg();
    }

    public static function apistatus() {
        return (bool) self::$cfg->getValue('retargeting/retargeting/status', ScopeInterface::SCOPE_STORE);
    }
//groups[retargeting][fields][status][value]
    public static function cfg($key = 'rec_status') {
        if ($key === 'rec_status') {
            return self::$cfg->getValue('retargeting/advanced_settings/'.$key, ScopeInterface::SCOPE_STORE);
        }
       
        $value = self::$cfg->getValue('retargeting/rec_data/'.$key, ScopeInterface::SCOPE_STORE);
        return self::$serializer->unserialize($value);
    }


    public static function rec_engine_load() {
        if (self::apistatus() && self::status()) {
            $ActionName = self::$_req->getFullActionName();
            if (isset(self::$rec_engine[$ActionName])) {
                return '
                var _ra_rec_engine = {};
    
                _ra_rec_engine.init = function () {
                    let list = this.list;
                    for (let key in list) {
                        _ra_rec_engine.insert(list[key].value, list[key].selector, list[key].place);
                    }
                };
    
                _ra_rec_engine.insert = function (code = "", selector = null, place = "before") {
                    if (code !== "" && selector !== null) {
                        let newTag = document.createRange().createContextualFragment(code);
                        let content = document.querySelector(selector);
    
                        content.parentNode.insertBefore(newTag, place === "before" ? content : content.nextSibling);
                    }
                };
                _ra_rec_engine.list = '.json_encode(self::cfg(self::$rec_engine[$ActionName])).';
                _ra_rec_engine.init();';
            }
        }

        return "";
    }

    /** @noinspection PhpUnused */
    protected function _toHtml()
    {
        /*self::rec_engine_load()
        console.log("'.self::$_req->getFullActionName().'","RTG")
        */
        return '<script type="text/javascript">'.self::rec_engine_load().'</script>';
    }

}
