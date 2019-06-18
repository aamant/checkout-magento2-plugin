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
 * Class ConfigGooglePayEnvironment
 */
class ConfigGooglePayEnvironment implements \Magento\Framework\Option\ArrayInterface
{

    const ENVIRONMENT_TEST = 'TEST';
    const ENVIRONMENT_PRODUCTION = 'PRODUCTION';

    /**
     * Possible environment types
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => self::ENVIRONMENT_TEST,
                'label' => __('Test')
            ],
            [
                'value' => self::ENVIRONMENT_PRODUCTION,
                'label' => __('Production')
            ]
        ];
    }
}
