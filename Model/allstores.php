<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Retargeting\Tracker\Model;

class allstores implements \Magento\Framework\Option\ArrayInterface
{
    private $_storeManager;
    public function __construct(
        \Magento\Store\Model\StoreManagerInterface $storeManager
    )
    {
        $this->_storeManager = $storeManager;
    }
    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        $storeManagerDataList = $this->_storeManager->getStores();
        $options = array();

        foreach ($storeManagerDataList as $key => $value) {
            $options[] = ['label' => $value['name'], 'value' => $key];
        }

        return $options;
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        $storeManagerDataList = $this->_storeManager->getStores();
        $options = array();

        foreach ($storeManagerDataList as $key => $value) {
            $options[$key] = $value['name'];
        }

        return $options;
    }
}
