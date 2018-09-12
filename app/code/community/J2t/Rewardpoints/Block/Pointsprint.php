<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@j2t-design.com so we can send you a copy immediately.
 *
 * @category   Magento extension
 * @package    RewardsPoint2
 * @copyright  Copyright (c) 2009 J2T DESIGN. (http://www.j2t-design.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class J2t_Rewardpoints_Block_Pointsprint extends J2t_Rewardpoints_Block_Points
{
    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('referafriend/points.phtml');
        //->addAttributeToSort('rewardpoints_account_id', 'ASC')
        $points = Mage::getModel('rewardpoints/stats')->getCollection();
        $points->addClientFilter(Mage::getSingleton('customer/session')->getCustomer()->getId());
        $points->getSelect()->order('rewardpoints_account_id DESC');
        
        $from_date = Mage::app()->getRequest()->getParam('from_date');
        $to_date = Mage::app()->getRequest()->getParam('to_date');
        
        if ($from_date) {
            $points->getSelect()->where('date_insertion >= ?', $from_date);
        }
        if ($to_date) {
            $points->getSelect()->where('date_insertion < ?', $to_date);
        }
        
        $points->getSelect()->limit();
        $points->setPageSize(0);
        
        //$points->load();
        $this->setPoints($points);
    }

    public function _prepareLayout()
    {
        parent::_prepareLayout();
        $this->getPoints()->load();

        return $this;
    }
    
}