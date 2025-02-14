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

namespace CheckoutCom\Magento2\Controller\Apm;

use Checkout\CheckoutApi;
use Checkout\Library\HttpHandler;
use Checkout\Models\Sources\Sepa;
use Checkout\Models\Sources\SepaData;
use Checkout\Models\Sources\SepaAddress;

/**
 * Class DisplaySepa
 */
class DisplaySepa extends \Magento\Framework\App\Action\Action
{

    /**
     * @var Context
     */
    public $context;

    /**
     * @var PageFactory
     */
    public $pageFactory;

    /**
     * @var JsonFactory
     */
    public $jsonFactory;

    /**
     * @var Config
     */
    public $config;

    /**
     * @var CheckoutApi
     */
    public $apiHandler;

    /**
     * @var QuoteHandlerService
     */
    public $quoteHandler;

    /**
     * @var Quote
     */
    public $quote;

    /**
     * @var Address
     */
    public $billingAddress;

    /**
     * @var Store
     */
    public $store;

    /**
     * @var Logger
     */
    public $logger;

    /**
     * Display constructor
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $pageFactory,
        \Magento\Framework\Controller\Result\JsonFactory $jsonFactory,
        \CheckoutCom\Magento2\Gateway\Config\Config $config,
        \CheckoutCom\Magento2\Model\Service\ApiHandlerService $apiHandler,
        \CheckoutCom\Magento2\Model\Service\QuoteHandlerService $quoteHandler,
        \Magento\Store\Model\Information $storeManager,
        \Magento\Store\Model\Store $storeModel,
        \CheckoutCom\Magento2\Helper\Logger $logger
    ) {
        parent::__construct($context);

        $this->pageFactory = $pageFactory;
        $this->jsonFactory = $jsonFactory;
        $this->config = $config;
        $this->apiHandler = $apiHandler;
        $this->quoteHandler = $quoteHandler;
        $this->storeManager = $storeManager;
        $this->storeModel = $storeModel;
        $this->logger = $logger;
    }

    /**
     * Handles the controller method.
     */
    public function execute()
    {
        // Prepare the output container
        $html = '';

        try {
            // Get the request parameters
            $this->source = $this->getRequest()->getParam('source');
            $this->task = $this->getRequest()->getParam('task');
            $this->bic = $this->getRequest()->getParam('bic');
            $this->account_iban = $this->getRequest()->getParam('account_iban');

            // Try to load a quote
            $this->quote = $this->quoteHandler->getQuote();
            $this->billingAddress = $this->quoteHandler->getBillingAddress();
            $this->store = $this->storeManager->getStoreInformationObject($this->storeModel);

            // Run the requested task
            if ($this->isValidRequest()) {
                $html = $this->runTask();
            }
        } catch (\Exception $e) {
            $this->logger->write($e->getMessage());
        } finally {
            return $this->jsonFactory->create()->setData(
                ['html' => $html]
            );
        }
    }

    /**
     * Checks if the request is valid.
     *
     * @return boolean
     */
    public function isValidRequest()
    {
        try {
            return $this->getRequest()->isAjax()
            && $this->isValidApm()
            && $this->isValidTask();
        } catch (\Exception $e) {
            $this->logger->write($e->getMessage());
            return false;
        }
    }

    /**
     * Checks if the task is valid.
     *
     * @return boolean
     */
    public function isValidTask()
    {
        try {
            return method_exists($this, $this->buildMethodName());
        } catch (\Exception $e) {
            $this->logger->write($e->getMessage());
            return false;
        }
    }

    /**
     * Runs the requested task.
     *
     * @return string
     */
    public function runTask()
    {
        try {
            $methodName = $this->buildMethodName();
            return $this->$methodName();
        } catch (\Exception $e) {
            $this->logger->write($e->getMessage());
            return '';
        }
    }

    /**
     * Builds a method name from request.
     *
     * @return string
     */
    public function buildMethodName()
    {
        try {
            return 'get' . ucfirst($this->task);
        } catch (\Exception $e) {
            $this->logger->write($e->getMessage());
            return '';
        }
    }

    /**
     * Checks if the requested APM is valid.
     *
     * @return boolean
     */
    public function isValidApm()
    {
        try {
            // Get the list of APM
            $apmEnabled = explode(
                ',',
                $this->config->getValue('apm_enabled', 'checkoutcom_apm')
            );

            // Load block data for each APM
            return in_array($this->source, $apmEnabled) ? true : false;
        } catch (\Exception $e) {
            $this->logger->write($e->getMessage());
            return false;
        }
    }

    /**
     * Returns the SEPA mandate block.
     *
     * @return string
     */
    public function loadBlock($reference, $url)
    {
        try {
            return $this->pageFactory->create()->getLayout()
                ->createBlock('CheckoutCom\Magento2\Block\Apm\Sepa\Mandate')
                ->setTemplate('CheckoutCom_Magento2::payment/apm/sepa/mandate.phtml')
                ->setData('billingAddress', $this->billingAddress)
                ->setData('store', $this->store)
                ->setData('reference', $reference)
                ->setData('url', $url)
                ->toHtml();
        } catch (\Exception $e) {
            $this->logger->write($e->getMessage());
            return '';
        }
    }

    /**
     * Gets the mandate.
     *
     * @return string
     */
    public function getMandate()
    {
        $html = '';

        try {
            $sepa = $this->requestSepa();
            if ($sepa && $sepa->isSuccessful()) {
                $html = $this->loadBlock($sepa->response_data['mandate_reference'], $sepa->getSepaMandateGet());
            }
        } catch (\Exception $e) {
            $this->logger->write($e->getMessage());
        } finally {
            return $html;
        }
    }

    /**
     * Request gateway to add new source.
     *
     * @return Sepa
     */
    public function requestSepa()
    {
        $sepa = null;
        try {
            // Initialize the API handler
            $api = $this->apiHandler->init();

            // Build the address
            $address = new SepaAddress(
                $this->billingAddress->getStreetLine(1),
                $this->billingAddress->getCity(),
                $this->billingAddress->getPostcode(),
                $this->billingAddress->getCountryId()
            );

            // Address line 2
            $address->address_line2 = $this->billingAddress->getStreetLine(2);

            // Build the SEPA data
            $data = new SepaData(
                $this->billingAddress->getFirstname(),
                $this->billingAddress->getLastname(),
                $this->account_iban,
                $this->bic,
                $this->config->getStoreName(),
                'single'
            );

            // Build and addthe source
            $source = new Sepa($address, $data);
            $sepa = $api->checkoutApi
                ->sources()
                ->add($source);
        } catch (\Exception $e) {
            $this->logger->write($e->getMessage());
        } finally {
            return $sepa;
        }
    }
}
