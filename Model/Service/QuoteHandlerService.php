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

use Magento\Customer\Api\Data\GroupInterface;

/**
 * Class QuoteHandlerService.
 */
class QuoteHandlerService
{
    /**
     * @var Session
     */
    public $checkoutSession;

    /**
     * @var Session
     */
    public $customerSession;

    /**
     * @var CookieManagerInterface
     */
    public $cookieManager;

    /**
     * @var QuoteFactory
     */
    public $quoteFactory;

    /**
     * @var StoreManagerInterface
     */
    public $storeManager;

    /**
     * @var ProductRepositoryInterface
     */
    public $productRepository;

    /**
     * @var Config
     */
    public $config;

    /**
     * @var ShopperHandlerService
     */
    public $shopperHandler;

    /**
     * @var Logger
     */
    public $logger;

    /**
     * QuoteHandlerService constructor
     */
    public function __construct(
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Framework\Stdlib\CookieManagerInterface $cookieManager,
        \Magento\Quote\Model\QuoteFactory $quoteFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \CheckoutCom\Magento2\Gateway\Config\Config $config,
        \CheckoutCom\Magento2\Model\Service\ShopperHandlerService $shopperHandler,
        \CheckoutCom\Magento2\Helper\Logger $logger
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->customerSession = $customerSession;
        $this->cookieManager = $cookieManager;
        $this->quoteFactory = $quoteFactory;
        $this->storeManager = $storeManager;
        $this->productRepository = $productRepository;
        $this->config = $config;
        $this->shopperHandler = $shopperHandler;
        $this->logger = $logger;
    }

    /**
     * Find a quote
     */
    public function getQuote($fields = [])
    {
        try {
            if (!empty($fields)) {
                // Get the quote factory
                $quoteFactory = $this->quoteFactory
                    ->create()
                    ->getCollection();

                // Add search filters
                foreach ($fields as $key => $value) {
                    $quoteFactory->addFieldToFilter(
                        $key,
                        $value
                    );
                }

                // Return the first result found
                return $quoteFactory
                    ->setPageSize(1)
                    ->getLastItem();
            } else {
                // Try to find the quote in session
                return $this->checkoutSession->getQuote();
            }
        } catch (\Exception $e) {
            $this->logger->write($e->getMessage());
            return null;
        }
    }

    /**
     * Create a new quote
     */
    public function createQuote($currency = null, $customer = null)
    {
        try {
            // Create the quote instance
            $quote = $this->quoteFactory->create();
            $quote->setStore($this->storeManager->getStore());

            // Set the currency
            if ($currency) {
                $quote->setCurrency($currency);
            } else {
                $quote->setCurrency();
            }

            // Set the quote customer
            if ($customer) {
                $quote->assignCustomer($customer);
            } else {
                $quote->assignCustomer($this->shopperHandler->getCustomerData());
            }

            return $quote;
        } catch (\Exception $e) {
            $this->logger->write($e->getMessage());
            return null;
        }
    }

    /**
     * Checks if a quote exists and is valid
     */
    public function isQuote($quote)
    {
        try {
            return $quote
            && is_object($quote)
            && method_exists($quote, 'getId')
            && $quote->getId() > 0;
        } catch (\Exception $e) {
            $this->logger->write($e->getMessage());
            return null;
        }
    }

    /**
     * Get the order increment id from a quote
     */
    public function getReference($quote)
    {
        try {
            return $quote->reserveOrderId()
                ->save()
                ->getReservedOrderId();
        } catch (\Exception $e) {
            $this->logger->write($e->getMessage());
            return '';
        }
    }

    /**
     * Prepares a quote for order placement
     */
    public function prepareQuote($methodId, $fields = [], $isWebhook = false)
    {
        try {
            // Find quote and perform tasks
            $quote = $this->getQuote($fields);
            if ($this->isQuote($quote)) {
                // Prepare the inventory
                $quote->setInventoryProcessed(false);

                // Check for guest user quote
                if (!$this->customerSession->isLoggedIn() && !$isWebhook) {
                    $quote = $this->prepareGuestQuote($quote);
                }

                // Set the payment information
                $payment = $quote->getPayment();
                $payment->setMethod($methodId);
                $payment->save();

                return $quote;
            }
        } catch (\Exception $e) {
            $this->logger->write($e->getMessage());
            return null;
        }
    }

    /**
     * Sets the email for guest users
     */
    public function prepareGuestQuote($quote, $email = null)
    {
        try {
            // Retrieve the user email
            $guestEmail = ($email) ? $email : $this->findEmail($quote);

            // Set the quote as guest
            $quote->setCustomerId(null)
                ->setCustomerEmail($guestEmail)
                ->setCustomerIsGuest(true)
                ->setCustomerGroupId(GroupInterface::NOT_LOGGED_IN_ID);

            // Delete the cookie
            $this->cookieManager->deleteCookie(
                $this->config->getValue('email_cookie_name')
            );
        } catch (\Exception $e) {
            $this->logger->write($e->getMessage());
        } finally {
            return $quote;
        }
    }

    /**
     * Find a customer email
     */
    public function findEmail($quote)
    {
        try {
            // Get an array of possible values
            $emails = [
                $quote->getCustomerEmail(),
                $quote->getBillingAddress()->getEmail(),
                $this->cookieManager->getCookie(
                    $this->config->getValue('email_cookie_name')
                )
            ];

            // Return the first available value
            foreach ($emails as $email) {
                if ($email && !empty($email)) {
                    return $email;
                }
            }

            return null;
        } catch (\Exception $e) {
            $this->logger->write($e->getMessage());
            return null;
        }
    }

    /**
     * Gets an array of quote parameters
     */
    public function getQuoteData()
    {
        try {
            return [
                'value' => $this->getQuoteValue(),
                'currency' => $this->getQuoteCurrency()
            ];
        } catch (\Exception $e) {
            $this->logger->write($e->getMessage());
            return null;
        }
    }

    /**
     * Gets a quote currency
     */
    public function getQuoteCurrency()
    {
        try {
            $quoteCurrencyCode = $this->getQuote()->getQuoteCurrencyCode();
            $storeCurrencyCode = $this->storeManager->getStore()->getCurrentCurrency()->getCode();
            return ($quoteCurrencyCode) ? $quoteCurrencyCode : $storeCurrencyCode;
        } catch (\Exception $e) {
            $this->logger->write($e->getMessage());
            return null;
        }
    }

    /**
     * Gets a quote value
     */
    public function getQuoteValue()
    {
        try {
            return $this->getQuote()
                ->collectTotals()
                ->save()
                ->getGrandTotal();
        } catch (\Exception $e) {
            $this->logger->write($e->getMessage());
            return null;
        }
    }

    /**
     * Add product items to a quote
     */
    public function addItems($quote, $data)
    {
        try {
            $items = $this->buildProductData($data);
            foreach ($items as $item) {
                if (isset($item['product_id']) && (int) $item['product_id'] > 0) {
                    // Load the product
                    $product = $this->productRepository->getById($item['product_id']);

                    // Get the quantity
                    $quantity = isset($item['qty']) && (int) $item['qty'] > 0
                    ? $item['qty'] : 1;

                    // Add the item
                    if (!empty($item['super_attribute'])) {
                        $buyRequest = new \Magento\Framework\DataObject($item);
                        $quote->addProduct($product, $buyRequest);
                    } else {
                        $quote->addProduct($product, $quantity);
                    }
                }
            }
        } catch (\Exception $e) {
            $this->logger->write($e->getMessage());
        } finally {
            return $quote;
        }
    }

    /**
     * Creates a formatted array with the purchased product data.
     *
     * @return array
     */
    public function buildProductData($data)
    {
        try {
            // Prepare the base array
            $output =[
                'product_id' => $data['product'],
                'qty' => $data['qty']
            ];

            // Add product variations
            if (isset($data['super_attribute']) && !empty($data['super_attribute'])) {
                $output['super_attribute'] = $data['super_attribute'];
            }

            return [$output];
        } catch (\Exception $e) {
            $this->logger->write($e->getMessage());
            return [];
        }
    }

    /* Gets the billing address.
     *
     * @return     Address  The billing address.
     */
    public function getBillingAddress()
    {
        try {
            return $this->getQuote()->getBillingAddress();
        } catch (\Exception $e) {
            $this->logger->write($e->getMessage());
            return null;
        }
    }

    /* Gets quote data for a payment request.
     *
     * @return array
     */
    public function getQuoteRequestData($quote)
    {
        try {
            return [
            'quote_id' => $quote->getId(),
            'store_id' => $quote->getStoreId(),
            'customer_email' => $quote->getCustomerEmail()
            ];
        } catch (\Exception $e) {
            $this->logger->write($e->getMessage());
            return [];
        }
    }
}
