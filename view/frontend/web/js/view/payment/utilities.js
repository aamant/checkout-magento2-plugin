define(
    [
        'jquery',
        'CheckoutCom_Magento2/js/view/payment/config-loader',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/checkout-data',
        'mage/url',
        'Magento_Checkout/js/action/redirect-on-success',
        'Magento_Checkout/js/model/full-screen-loader',
        'mage/translate',
        'mage/cookies'
    ],
    function ($, Config, Quote, CheckoutData, Url, RedirectOnSuccessAction, FullScreenLoader, __) {
        'use strict';

        const KEY_CONFIG = 'checkoutcom_configuration';
        const KEY_DATA = 'checkoutcom_data';
        
        return {
            /**
             * Gets a field value.
             *
             * @param  {string}  methodId The method id
             * @param  {string}  field    The field
             * @return {mixed}            The value
             */
            getValue: function (methodId, field) {
                var val = null;
                if (methodId && Config.hasOwnProperty(methodId) && Config[methodId].hasOwnProperty(field)) {
                    val = Config[methodId][field]
                } else if (Config.hasOwnProperty(KEY_CONFIG) && Config[KEY_CONFIG].hasOwnProperty(field)) {
                    val = Config[KEY_CONFIG][field];
                }

                return val;
            },

            /**
             * Get the store name.
             *
             * @return {string}  The store name.
             */
            getStoreName: function () {
                return Config[KEY_DATA].store.name;
            },

            /**
             * Get the quote value.
             *
             * @return {float}  The quote value.
             */
            getQuoteValue: function () {
                var data = this.getRestQuoteData();
                var amount = parseFloat(data.totals.base_grand_total);

                return amount.toFixed(2);
            },

            /**
             * Get the updated quote data from the core REST API.
             *
             * @return {object}  The quote data.
             */
            getRestQuoteData: function() {
                // Prepare the required parameters
                var self = this;
                var result = null;

                // Build the URL
                var restUrl = window.BASE_URL;
                restUrl += 'rest/default/V1/carts/mine/payment-information';
                restUrl += '?form_key=' + window.checkoutConfig.formKey;

                // Set the event to update data on any button click
                $('button[type="submit"]')
                .off('click', self.getRestQuoteData)
                .on('click', self.getRestQuoteData);

                // Send the AJAX request
                $.ajax({
                    url: restUrl,
                    type: 'GET',
                    contentType: "application/json",
                    dataType: "json",
                    async: false,
                    showLoader: true,
                    success: function(data, status, xhr) {
                        result = data;
                    },
                    error: function (request, status, error) {
                        self.log(error);
                    }
                });

                return result;
            },

            /**
             * Get the quote currency.
             *
             * @return {string}  The quote currency.
             */
            getQuoteCurrency: function () {
                return Config[KEY_DATA].quote.currency;
            },

            /**
             * Checks if user has saved cards.
             *
             * @return {bool}
             */
            userHasCards: function () {
                return Config[KEY_DATA].user.hasCards;
            },

            /**
             * Get the supported cards.
             *
             * @return {array}
             */
            getSupportedCards: function () {
                return Config[KEY_DATA].cards;
            },

            /**
             * Builds the controller URL.
             *
             * @param  {string}  path  The path
             * @return {string}
             */
            getUrl: function (path) {
                return Url.build('checkout_com/' + path);
            },

            /**
             * Customer name.
             *
             * @param  {bool} return in object format.
             * @return {mixed}  The billing address.
             */
            getCustomerName: function () {
                var billingAddress = Quote.billingAddress();
                var customerName = '';
                if (billingAddress) {
                    customerName += billingAddress.firstname;
                    customerName += ' ' + billingAddress.lastname;
                }

                return customerName;
            },

            /**
             * Billing address.
             *
             * @return {object}  The billing address.
             */
            getBillingAddress: function () {
                return Quote.billingAddress();
            },

            /**
             * @returns {string}
             */
            getEmail: function () {
                return window.checkoutConfig.customerData.email || Quote.guestEmail || CheckoutData.getValidatedEmailValue();
            },

            /**
             * @returns {void}
             */
            setEmail: function () {
                var userEmail = this.getEmail();
                var emailCookieName = this.getValue(null, 'email_cookie_name');
                $.cookie(emailCookieName, userEmail);

                // If no email found, observe the core email field
                if (!userEmail) {
                    $('#customer-email').off('change').on('change', function () {
                        userEmail = Quote.guestEmail || CheckoutData.getValidatedEmailValue();
                        $.cookie(emailCookieName, userEmail);
                    });
                }
            },

            /**
             * @returns {object}
             */
            getPhone: function () {
                var billingAddress = Quote.billingAddress();

                return {
                    number: billingAddress.telephone
                };
            },

            /**
             * Handle error logging.
             */
            log: function (val) {
                if (this.getValue(null, 'debug')
                    && this.getValue(null, 'console_logging')
                ) {
                    console.log(val);
                }
            },

            /**
             * Show a message.
             */
            showMessage: function (type, message, methodId) {
                this.clearMessages(methodId);
                var messageContainer = this.getMethodContainer(methodId).find('.message');
                messageContainer.addClass('message-' + type + ' ' + type);
                messageContainer.append('<div>' + __(message) + '</div>');
                messageContainer.show();
            },

            /**
             * Clear all messages.
             */
            clearMessages: function (methodId) {
                var messageContainer = this.getMethodContainer(methodId).find('.message');
                messageContainer.hide();
                messageContainer.empty();
            },

            /**
             * Get a payment method container instance.
             */
            getMethodContainer: function (methodId) {
                return $('#' + methodId + '_container');
            },

            /**
             * Check if an URL is valid.
             */
            isUrl: function (str) {
                var pattern = /^(?:\w+:)?\/\/([^\s\.]+\.\S{2}|localhost[\:?\d]*)\S*$/;
                return pattern.test(str);
            },

            /**
             * Handle the place order button state.
             */
            allowPlaceOrder: function (buttonId, yesNo) {
                $('#' + buttonId).prop('disabled', !yesNo);
            },

            /**
             * Place a new order.
             *
             * @returns {void}
             */
            placeOrder: function (payload, methodId) {
                var self = this;

                // Start the loader
                FullScreenLoader.startLoader();

                // Send the request
                $.ajax({
                    type: 'POST',
                    url: self.getUrl('payment/placeorder'),
                    data: payload,
                    success: function (data) {
                        if (!data.success) {
                            FullScreenLoader.stopLoader();
                            self.showMessage('error', data.message, methodId);
                        } else if (data.success && data.url) {
                            // Handle 3DS redirection
                            window.location.href = data.url
                        } else {
                            // Normal redirection
                            RedirectOnSuccessAction.execute();
                        }
                    },
                    error: function (request, status, error) {
                        self.showMessage('error', error, methodId);
                        FullScreenLoader.stopLoader();
                    }
                });
            }
        };
    }
);
