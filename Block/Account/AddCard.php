<?php

/**
 * Checkout.com
 * Authorized and regulated as an electronic money institution
 * by the UK Financial Conduct Authority (FCA) under number 900816.
 *
 * PHP version 7
 *
 * @category  Magento2
 * @package   Checkout.com
 * @author    Platforms Development Team <platforms@checkout.com>
 * @copyright 2010-2019 Checkout.com
 * @license   https://opensource.org/licenses/mit-license.html MIT License
 * @link      https://docs.checkout.com/
 */

namespace CheckoutCom\Magento2\Block\Account;

/**
 * Class AddCard
 */
class AddCard extends \Magento\Framework\View\Element\Template
{
    /**
     * @var Config
     */
    public $config;

    /**
     * AddCard constructor.
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \CheckoutCom\Magento2\Gateway\Config\Config $config,
        array $data = []
    ) {
        $this->config = $config;
        parent::__construct($context, $data);
    }
}
