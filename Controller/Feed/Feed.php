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
use Retargeting\Tracker\Helper\FeedHelper;

/**
 * Class Feed
 * @package Retargeting\Tracker\Controller\Feed
 */
class Feed extends Action
{
    protected $_retargetingFeed;
    public function __construct(Context $context, FeedHelper $_retargetingFeed) {
        parent::__construct($context);
        $this->_retargetingFeed = $_retargetingFeed;
    }

    public function execute() {
        return $this->_retargetingFeed->genFeed();
    }
}
