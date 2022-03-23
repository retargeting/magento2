<?php

namespace Retargeting\Tracker\Cron;

use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;

use Retargeting\Tracker\Helper\FeedHelper;

class GenerateFeed {

    private static $isExec = false;

    public function __construct(Context $context, FeedHelper $_retargetingFeed, JsonFactory $resultJsonFactory) {
        $this->_retargetingFeed = $_retargetingFeed;
        $this->resultJsonFactory = $resultJsonFactory;
    }


    public function execute()
    {
        $resultJson = $this->resultJsonFactory->create();

        if (!self::$isExec && $this->_retargetingFeed->cronActive !== 0) {
            self::$isExec = true;
            
            return $resultJson->setData($this->_retargetingFeed->cronFeed());
        }

        return $resultJson->setData(['status' => true]);
    }
}
