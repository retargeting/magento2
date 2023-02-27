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

namespace Retargeting\Tracker\Controller\Feed;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\ResultFactory;
use Retargeting\Tracker\Helper\Data;
use Retargeting\Tracker\Helper\FeedHelper;

/**
 * Class Feed
 * @package Retargeting\Tracker\Controller\Feed
 */
class debugProduct extends Action
{
    public function __construct(Context $context, FeedHelper $_retargetingFeed, Http $request, Data $_retargetingData) {
        parent::__construct($context);
        $this->_retargetingFeed = $_retargetingFeed;
        $this->_retargetingData = $_retargetingData;
        $this->request = $request;
    }

    public function execute() {
        $restKey = $this->_retargetingData->getCfg(\Retargeting\Tracker\Helper\Data::RETARGETING_REST_API, null);

        if ($restKey && $this->request->getParam('k') === $restKey) {
            $json = $this->request->getParam('json');
            if ($json === true || $json === 'true') {
                $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
                $result->setHeader('Content-Type', 'application/json');

                $product = $this->_retargetingFeed->debugFeedForProductId($this->request->getParam('id'), $json);
                return $result->setData($product);
            }
            $this->_retargetingFeed->debugFeedForProductId($this->request->getParam('id'));
        }
    }
}
