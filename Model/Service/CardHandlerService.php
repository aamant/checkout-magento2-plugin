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

namespace CheckoutCom\Magento2\Model\Service;

/**
 * Class CardHandlerService.
 */
class CardHandlerService
{
    /**
     * @var array
     */
    public static $cardMapper = [
        'VI' => 'Visa',
        'MC' => 'Mastercard',
        'AE' => 'American Express',
        'DN' => 'Diners Club International',
        'DI' => 'Discover',
        'JCB' => 'JCB'
    ];

    /**
     * @var Repository
     */
    public $assetRepository;

    /**
     * @var Reader
     */
    public $directoryReader;

    /**
     * @var Csv
     */
    public $csvParser;

    /**
     * @var Config
     */
    public $config;

    /**
     * @var Logger
     */
    public $logger;

    /**
     * CardHandlerService constructor.
     */
    public function __construct(
        \Magento\Framework\View\Asset\Repository $assetRepository,
        \Magento\Framework\Module\Dir\Reader $directoryReader,
        \Magento\Framework\File\Csv $csvParser,
        \CheckoutCom\Magento2\Gateway\Config\Config $config,
        \CheckoutCom\Magento2\Helper\Logger $logger
    ) {
        $this->assetRepository = $assetRepository;
        $this->directoryReader = $directoryReader;
        $this->csvParser = $csvParser;
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * Get a card code from name.
     *
     * @return string
     */
    public function getCardCode($scheme)
    {
        try {
            return array_search(
                $scheme,
                self::$cardMapper
            );
        } catch (\Exception $e) {
            $this->logger->write($e->getMessage());
            return '';
        }
    }

    /**
     * Get a card scheme from code.
     *
     * @return string
     */
    public function getCardScheme($code)
    {
        try {
            return self::$cardMapper[$code];
        } catch (\Exception $e) {
            $this->logger->write($e->getMessage());
            return '';
        }
    }

    /**
     * Get a card icon.
     *
     * @return string
     */
    public function getCardIcon($code)
    {
        try {
            return $this->assetRepository
                ->getUrl(
                    'CheckoutCom_Magento2::images/cc/' . strtolower($code) . '.svg'
                );
        } catch (\Exception $e) {
            $this->logger->write($e->getMessage());
            return '';
        }
    }

    /**
     * Get all card icons.
     *
     * @return string
     */
    public function getCardIcons()
    {
        try {
            // Prepare the output array
            $output = [];

            // Get the selected cards
            $selectedCards = explode(
                ',',
                $this->config->getValue(
                    'card_icons',
                    'checkoutcom_card_payment'
                )
            );

            // Build the cards list
            foreach (self::$cardMapper as $code => $value) {
                if (in_array($code, $selectedCards)) {
                    $output[] = [
                        'code' => $code,
                        'name' => __($value),
                        'url' => $this->assetRepository
                        ->getUrl(
                            'CheckoutCom_Magento2::images/cc/' . strtolower($code) . '.svg'
                        )
                    ];
                }
            }

            return $output;
        } catch (\Exception $e) {
            $this->logger->write($e->getMessage());
            return '';
        }
    }

    /**
     * Check if a card is active.
     *
     * @return bool
     */
    public function isCardActive($card)
    {
        try {
            return $card->getIsActive()
            && $card->getIsVisible()
            && $card->getPaymentMethodCode() == 'checkoutcom_vault';
        } catch (\Exception $e) {
            $this->logger->write($e->getMessage());
            return false;
        }
    }

    /**
     * Checks the MADA BIN
     *
     * @return bool
     */
    public function isMadaBin($bin)
    {
        try {
            // Set the root path
            $csvPath = $this->directoryReader->getModuleDir(
                '',
                'CheckoutCom_Magento2'
            )  . '/' . $this->config->getMadaBinFile();

            // Get the data
            $csvData = $this->csvParser->getData($csvPath);

            // Remove the first row of csv columns
            unset($csvData[0]);

            // Build the MADA BIN array
            $binArray = [];
            foreach ($csvData as $row) {
                $binArray[] = $row[1];
            }

            return in_array($bin, $binArray);
        } catch (\Exception $e) {
            $this->logger->write($e->getMessage());
            return false;
        }
    }
}
