<?php

namespace Retargeting\Tracker\Controller\Feed;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Retargeting\Tracker\Helper\FeedHelper;

class Cron extends Action {
    protected $_retargetingFeed;
    public function __construct(Context $context, FeedHelper $_retargetingFeed) {
        parent::__construct($context);
        $this->_retargetingFeed = $_retargetingFeed;
    }

    public function execute()
    {
        $this->_retargetingFeed->cronFeed();

        header("Content-type: text/json; charset=utf-8");

        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');

        echo '{"status":"success"}';
    }
}

