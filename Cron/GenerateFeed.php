<?php

namespace Retargeting\Tracker\Cron;

use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;

use Retargeting\Tracker\Helper\FeedHelper;

class GenerateFeed {

    protected $_retargetingFeed;
    protected $resultJsonFactory;
    protected $logger;

    public function __construct(Context $context, FeedHelper $_retargetingFeed, JsonFactory $resultJsonFactory, \Psr\Log\LoggerInterface $logger) {
        $this->logger = $logger;
        $this->_retargetingFeed = $_retargetingFeed;
        $this->resultJsonFactory = $resultJsonFactory;
    }


    public function execute()
    {
        $this->logger->info('Cron Retargeting Works');

        try {
            if ($this->_retargetingFeed->cronActive !== 0) {

                $this->_retargetingFeed->cronFeed();
            }
        } catch (\Exception $e) {
            $this->logger->critical('Error message', ['exception' => $e]);
        }
    }
}
