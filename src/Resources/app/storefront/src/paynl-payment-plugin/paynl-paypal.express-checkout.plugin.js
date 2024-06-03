import Plugin from 'src/plugin-system/plugin.class';
import HttpClient from 'src/service/http-client.service';
import DomAccess from 'src/helper/dom-access.helper';
import ElementLoadingIndicatorUtil from 'src/utility/loading-indicator/element-loading-indicator.util';
import PaynlPayPalScriptLoading from './paynl-paypal.script-loading';
import { loadScript } from '../../node_modules/@paypal/paypal-js';

export default class PaynlPayPalExpressCheckoutButton extends Plugin {
    static scriptLoading = new PaynlPayPalScriptLoading();

    static options = {

        /**
         * This option defines the class name which will be added when the button gets disabled.
         *
         * @type string
         */
        disabledClass: 'is-disabled',

        /**
         * This option defines the selector for the buy button on the product detail page and listing.
         *
         * @type string
         */
        buyButtonSelector: '.btn-buy',

        /**
         * This option specifies the PayPal button color
         *
         * @type string
         */
        buttonColor: 'gold',

        /**
         * This option specifies the PayPal button shape
         *
         * @type string
         */
        buttonShape: 'rect',

        /**
         * This option specifies the PayPal button size
         *
         * @type string
         */
        buttonSize: 'small',

        /**
         * This option specifies the language of the PayPal button
         *
         * @type string
         */
        languageIso: 'en_GB',

        /**
         * This option holds the client id specified in the settings
         *
         * @type string
         */
        clientId: 'Ad37_s9hRiNiPtgdHxz0eGzN_1UWjkmU-GtDOJZgmuIUP6N-lzVkUVeEEthgYarcM6TPpQ4IL0i-fEUJ',

        /**
         * This option holds the merchant id specified in the settings
         *
         * @type string
         */
        merchantPayerId: '',

        /**
         * This options specifies the currency of the PayPal button
         *
         * @type string
         */
        currency: 'EUR',

        /**
         * This options defines the payment intent
         *
         * @type string
         */
        intent: 'capture',

        /**
         * This option toggles the PayNow/Login text at PayPal
         *
         * @type boolean
         */
        commit: false,

        /**
         * This option toggles the text below the PayPal Express button
         *
         * @type boolean
         */
        tagline: false,

        /**
         * This option toggles the Process whether or not the product needs to be added to the cart.
         *
         * @type boolean
         */
        addProductToCart: false,

        /**
         * URL to set payment method to PayPal
         *
         * @type string
         */
        contextSwitchUrl: '',

        /**
         * @type string
         */
        payPalPaymentMethodId: '',

        /**
         * URL to create a new PayPal order
         *
         * @type string
         */
        createOrderUrl: '',

        /**
         * URL to delete an existing cart in Shopware
         *
         * @type string
         */
        deleteCartUrl: '',

        /**
         * URL for creating and logging in guest customer
         *
         * @type string
         */
        prepareCheckoutUrl: '',

        /**
         * URL to the checkout confirm page
         *
         * @type string
         */
        checkoutConfirmUrl: '',

        /**
         * URL for adding flash error message
         *
         * @type string
         */
        addErrorUrl: '',

        /**
         * URL for redirecting to after user cancels
         *
         * @type string
         */
        cancelRedirectUrl: '',

        /**
         * Show additional pay later button
         *
         * @type boolean
         */
        showPayLater: true,

        /**
         * Show no other buttons
         *
         * @type boolean
         */
        useAlternativePaymentMethods: false,
    };

    createScript(callback) {
        if (this.constructor.scriptLoading.paypal !== null) {
            callback.call(this, this.constructor.scriptLoading.paypal);
            return;
        }

        this.constructor.scriptLoading.callbacks.push(callback);

        if (this.constructor.scriptLoading.loadingScript) {
            return;
        }

        this.constructor.scriptLoading.loadingScript = true;

        loadScript(this.getScriptOptions()).then(this.callCallbacks.bind(this));
    }

    callCallbacks() {
        if (this.constructor.scriptLoading.paypal === null) {
            this.constructor.scriptLoading.paypal = window.paypal;
            delete window.paypal;
        }

        this.constructor.scriptLoading.callbacks.forEach((callback) => {
            callback.call(this, this.constructor.scriptLoading.paypal);
        });
    }

    /**
     * @return {Object}
     */
    getScriptOptions() {
        console.log(this.options);
        const config = {
            // components: 'buttons,messages,card-fields,funding-eligibility,applepay,googlepay',
            'client-id': this.options.clientId,
            debug: true,
            // commit: !!this.options.commit,
            // locale: this.options.languageIso,
            currency: this.options.currency,
            // intent: this.options.intent,
            // 'enable-funding': 'paylater,venmo',
        };

        if (this.options.disablePayLater || this.options.showPayLater === false) {
            config['enable-funding'] = 'venmo';
        }

        if (this.options.useAlternativePaymentMethods === false) {
            // config['disable-funding'] = availableAPMs.join(',');
        } else if (Array.isArray(this.options.disabledAlternativePaymentMethods)) {
            config['disable-funding'] = this.options.disabledAlternativePaymentMethods.join(',');
        }

        if (this.options.merchantPayerId) {
            config['merchant-id'] = this.options.merchantPayerId;
        }

        if (this.options.clientToken) {
            config['data-client-token'] = this.options.clientToken;
        }

        if (this.options.userIdToken) {
            config['data-user-id-token'] = this.options.userIdToken;
        }

        if (this.options.partnerAttributionId) {
            config['data-partner-attribution-id'] = this.options.partnerAttributionId;
        }

        return config;
    }

    /**
     * @param {'cancel'|'browser'|'error'} type
     * @param {*=} error
     * @param {String=} redirect
     * @returns {void}
     */
    createError(type, error = undefined, redirect = '') {
        if (process.env.NODE_ENV !== 'production' && typeof console !== 'undefined' && typeof this._client === 'undefined') {
            console.error('No HttpClient defined in child plugin class');
            return;
        }

        const addErrorUrl = this.options.addErrorUrl;
        if (process.env.NODE_ENV !== 'production'
            && typeof console !== 'undefined'
            && (typeof addErrorUrl === 'undefined' || addErrorUrl === null)
        ) {
            console.error('No "addErrorUrl" defined in child plugin class');
            return;
        }

        if (this.options.accountOrderEditCancelledUrl && this.options.accountOrderEditFailedUrl) {
            window.location = type === 'cancel' ? this.options.accountOrderEditCancelledUrl : this.options.accountOrderEditFailedUrl;

            return;
        }

        if (!!error && typeof error !== 'string') {
            error = String(error);
        }

        this._client.post(addErrorUrl, JSON.stringify({error, type}), () => {
            if (redirect) {
                window.location = redirect;
                return;
            }

            window.onbeforeunload = () => {
                window.scrollTo(0, 0);
            };
            window.location.reload();
        });
    }

    init() {
        this._client = new HttpClient();
        this.createButton();
        console.log('Init success');
    }

    createButton() {
        this.createScript((paypal) => {
            this.renderButton(paypal);
        });
    }

    renderButton(paypal) {
        return paypal.Buttons(this.getButtonConfig()).render(this.el);
    }

    getBuyButtonState() {
        if (!this.options.addProductToCart) {
            return {
                element: null,
                disabled: false,
            };
        }

        const element = DomAccess.querySelector(this.el.closest('form'), this.options.buyButtonSelector);

        return {
            element,
            disabled: element.disabled,
        };
    }

    observeBuyButton(target, enableButton, disableButton, config = { attributes: true }) {
        const callback = (mutations) => {
            mutations.forEach((mutation) => {
                if (mutation.attributeName === 'disabled') {
                    const { disabled: isBuyButtonDisabled } = this.getBuyButtonState();

                    if (isBuyButtonDisabled) {
                        disableButton();
                        return;
                    }
                    enableButton();
                }
            });
        };

        const observer = new MutationObserver(callback);
        observer.observe(target, config);

        return observer;
    }

    getButtonConfig() {
        const renderElement = this.el;
        const { element: buyButton, disabled: isBuyButtonDisabled } = this.getBuyButtonState();

        return {
            onInit: (data, actions) => {
                if (!this.options.addProductToCart) {
                    return;
                }

                /**
                 * Helper method which enables the paypal button
                 * @returns void
                 */
                const enableButton = () => {
                    actions.enable();
                    renderElement.classList.remove(this.options.disabledClass);
                };

                /**
                 * Helper method which disables the paypal button
                 * @returns void
                 */
                const disableButton = () => {
                    actions.disable();
                    renderElement.classList.add(this.options.disabledClass);
                };

                this.observeBuyButton(buyButton, enableButton, disableButton);

                // Set the initial state of the button
                if (isBuyButtonDisabled) {
                    disableButton();
                    return;
                }
                enableButton();
            },
            style: {
                size: this.options.buttonSize,
                shape: this.options.buttonShape,
                color: this.options.buttonColor,
                tagline: this.options.tagline,
                layout: 'vertical',
                label: 'checkout',
                height: 40,
            },

            /**
             * Will be called if the express button is clicked
             */
            createOrder: this.createOrder.bind(this),

            /**
             * Will be called if the payment process is approved by paypal
             */
            onApprove: this.onApprove.bind(this),

            /**
             * Will be called if the user cancels the checkout.
             */
            onCancel: this.onCancel.bind(this),

            /**
             * Will be called if an error occurs during the payment process.
             */
            onError: this.onError.bind(this),
        };
    }

    /**
     * @return {Promise}
     */
    createOrder() {
        const switchPaymentMethodData = {
            paymentMethodId: this.options.payPalPaymentMethodId,
            deleteCart: this.options.addProductToCart,
        };

        return new Promise((resolve, reject) => {
            this._client.post(
                this.options.contextSwitchUrl,
                JSON.stringify(switchPaymentMethodData),
                (responseText, request) => {
                    if (request.status >= 400) {
                        reject(responseText);
                    }

                    return Promise.resolve().then(() => {
                        if (this.options.addProductToCart) {
                            return this.addProductToCart();
                        }

                        return Promise.resolve();
                    }).then(() => {
                        return this._createOrder();
                    }).then(token => {
                        resolve(token);
                    })
                        .catch((error) => {
                            reject(error);
                        });
                },
            );
        });
    }

    /**
     * @return {Promise}
     */
    _createOrder() {
        return new Promise((resolve, reject) => {
            this._client.post(this.options.createOrderUrl, new FormData(), (responseText, request) => {
                if (request.status >= 400) {
                    reject(responseText);
                }

                try {
                    const response = JSON.parse(responseText);
                    resolve(response.token);
                } catch (error) {
                    reject(error);
                }
            });
        });
    }

    addProductToCart() {
        const buyForm = this.el.closest('form');
        const buyButton = DomAccess.querySelector(buyForm, this.options.buyButtonSelector);
        const plugin = window.PluginManager.getPluginInstanceFromElement(buyForm, 'AddToCart');

        return new Promise(resolve => {
            plugin.$emitter.subscribe('openOffCanvasCart', () => {
                resolve();
            });

            buyButton.click();
        });
    }

    onApprove(data, actions) {
        const requestPayload = {
            token: data.orderID,
        };

        // Add a loading indicator to the body to prevent the user breaking the checkout process
        ElementLoadingIndicatorUtil.create(document.body);

        this._client.post(
            this.options.prepareCheckoutUrl,
            JSON.stringify(requestPayload),
            (response, request) => {
                if (request.status < 400) {
                    return actions.redirect(this.options.checkoutConfirmUrl);
                }

                return this.createError('error', response, this.options.cancelRedirectUrl);
            },
        );
    }

    onError(error) {
        this.createError('error', error);
    }

    onCancel(error) {
        this.createError('cancel', error, this.options.cancelRedirectUrl);
    }
}
