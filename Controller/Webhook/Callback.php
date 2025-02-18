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

namespace CheckoutCom\Magento2\Controller\Webhook;

use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Webapi\Exception as WebException;
use Magento\Framework\Webapi\Rest\Response as WebResponse;

/**
 * Class Callback
 */
class Callback extends \Magento\Framework\App\Action\Action
{
    /**
     * @var array
     */
    public static $transactionMapper = [
        'payment_approved' => Transaction::TYPE_AUTH,
        'payment_captured' => Transaction::TYPE_CAPTURE,
        'payment_refunded' => Transaction::TYPE_REFUND,
        'payment_voided' => Transaction::TYPE_VOID
    ];

    /**
     * @var OrderRepositoryInterface
     */
    public $orderRepository;

    /**
     * @var apiHandler
     */
    public $apiHandler;

    /**
     * @var OrderHandlerService
     */
    public $orderHandler;

    /**
     * @var QuoteHandlerService
     */
    public $quoteHandler;

    /**
     * @var ShopperHandlerService
     */
    public $shopperHandler;

    /**
     * @var TransactionHandlerService
     */
    public $transactionHandler;

    /**
     * @var VaultHandlerService
     */
    public $vaultHandler;

    /**
     * @var Config
     */
    public $config;

    /**
     * @var Logger
     */
    public $logger;

    /**
     * Callback constructor
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \CheckoutCom\Magento2\Model\Service\apiHandlerService $apiHandler,
        \CheckoutCom\Magento2\Model\Service\OrderHandlerService $orderHandler,
        \CheckoutCom\Magento2\Model\Service\QuoteHandlerService $quoteHandler,
        \CheckoutCom\Magento2\Model\Service\ShopperHandlerService $shopperHandler,
        \CheckoutCom\Magento2\Model\Service\TransactionHandlerService $transactionHandler,
        \CheckoutCom\Magento2\Model\Service\VaultHandlerService $vaultHandler,
        \CheckoutCom\Magento2\Gateway\Config\Config $config,
        \CheckoutCom\Magento2\Helper\Logger $logger
    ) {
        parent::__construct($context);

        $this->orderRepository = $orderRepository;
        $this->apiHandler = $apiHandler;
        $this->orderHandler = $orderHandler;
        $this->quoteHandler = $quoteHandler;
        $this->shopperHandler = $shopperHandler;
        $this->transactionHandler = $transactionHandler;
        $this->vaultHandler = $vaultHandler;
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * Handles the controller method.
     */
    public function execute()
    {
        try {
            // Set the payload data
            $this->payload = $this->getPayload();

            // Prepare the response handler
            $resultFactory = $this->resultFactory->create(ResultFactory::TYPE_JSON);

            // Process the request
            if ($this->config->isValidAuth()) {
                // Process the request
                if (isset($this->payload->data->id)) {
                    // Initialize the API handler
                    $api = $this->apiHandler->init();

                    // Get the payment details
                    $response = $api->getPaymentDetails($this->payload->data->id);
                    if ($api->isValidResponse($response)) {
                        // Handle the save card request
                        if ($this->cardNeedsSaving()) {
                            $this->saveCard($response);
                        }

                        // Process the order
                        $order = $this->orderHandler
                            ->setMethodId($this->payload->data->metadata->methodId)
                            ->handleOrder(
                                $response,
                                ['increment_id' => $response->reference],
                                true
                            );

                        if ($this->orderHandler->isOrder($order)) {
                            // Handle the transaction
                            $order = $this->transactionHandler->createTransaction(
                                $order,
                                static::$transactionMapper[$this->payload->type],
                                $this->payload
                            );

                            // Save the order
                            $order = $this->orderRepository->save($order);

                            // Set a valid response
                            $resultFactory->setHttpResponseCode(WebResponse::HTTP_OK);
                            return $resultFactory->setData([
                                'result' => _('Webhook and order successfully processed.')
                            ]);
                        }
                        else {
                            $resultFactory->setHttpResponseCode(WebException::HTTP_INTERNAL_ERROR);
                            return $resultFactory->setData([
                                'error_message' => __('The order creation failed. Please check the error logs.')
                            ]);
                        }
                    }
                    else {
                        $resultFactory->setHttpResponseCode(WebException::HTTP_BAD_REQUEST);
                        return $resultFactory->setData([
                            'error_message' => __('The order was not created because of an invalid or failed payment.')
                        ]);                      
                    }
                }
                else {
                    $resultFactory->setHttpResponseCode(WebException::HTTP_BAD_REQUEST);
                    return $resultFactory->setData(
                        ['error_message' => __('The webhook payment response is invalid.')]
                    );                      
                }
            } else {
                $resultFactory->setHttpResponseCode(WebException::HTTP_UNAUTHORIZED);
                return $resultFactory->setData(['error_message' => _('Unauthorized request.')]);
            }
        } catch (\Exception $e) {
            $this->logger->write($e->getMessage());
            $resultFactory->setHttpResponseCode(WebException::HTTP_INTERNAL_ERROR);
            return $resultFactory->setData(['error_message' => $e->getMessage()]);
        }
    }

    /**
     * Get the request payload.
     */
    public function getPayload()
    {
        try {
            return json_decode($this->getRequest()->getContent());
        } catch (\Exception $e) {
            $this->logger->write($e->getMessage());
            return null;
        }
    }

    /**
     * Check if the card needs saving.
     */
    public function cardNeedsSaving()
    {
        try {
            return isset($this->payload->data->metadata->saveCard)
            && (int) $this->payload->data->metadata->saveCard == 1
            && isset($this->payload->data->metadata->customerId)
            && (int) $this->payload->data->metadata->customerId > 0
            && isset($this->payload->data->source->id)
            && !empty($this->payload->data->source->id);
        } catch (\Exception $e) {
            $this->logger->write($e->getMessage());
            return false;
        }
    }

    /**
     * Save a card.
     */
    public function saveCard($response)
    {
        try {
            // Get the customer
            $customer = $this->shopperHandler->getCustomerData(
                ['id' => $this->payload->data->metadata->customerId]
            );

            // Save the card
            $success = $this->vaultHandler
                ->setCardToken($this->payload->data->source->id)
                ->setCustomerId($customer->getId())
                ->setCustomerEmail($customer->getEmail())
                ->setResponse($response)
                ->saveCard();

            return $success;
        } catch (\Exception $e) {
            $this->logger->write($e->getMessage());
            return false;
        }
    }
}
