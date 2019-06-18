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

namespace CheckoutCom\Magento2\Model\Config\Backend\Source;

/**
 * Class ConfigApplePayCapabilities
 */
class ConfigApplePayCapabilities implements \Magento\Framework\Option\ArrayInterface
{

    const CAP_CRE = 'supportsCredit';
    const CAP_DEB = 'supportsDebit';

    /**
     * Possible Apple Pay Cards
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => self::CAP_CRE,
                'label' => __('Credit cards')
            ],
            [
                'value' => self::CAP_DEB,
                'label' => __('Debit cards')
            ],
        ];
    }
}
