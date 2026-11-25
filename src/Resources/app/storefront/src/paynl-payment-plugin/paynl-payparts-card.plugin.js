import Plugin from '../plugin-system/plugin.class';
import HttpClient from '../service/http-client.service';

const CARD_COMPONENT_SELECTOR = '#paynl-card-payments';
const ERROR_CLASS             = 'paynl-payparts-card-error';
const LOADING_CLASS           = 'is-loading';
const READY_CLASS             = 'is-ready';
const CONFIRM_FORM_ID         = 'confirmOrderForm';
const ORDER_FIELDS            = ['tos', 'revocation', 'customerComment', 'affiliateCode', 'campaignCode'];
const SDK_POLL_MS             = 100;
const SDK_WAIT_MS             = 10000;

/**
 * Shopware storefront plugin that mounts the PAY.Parts `card-payments` component.
 *
 * The Twig template renders this element when Pay.Parts is enabled and a card
 * payment method is available — no runtime payment-method selection is needed.
 *
 * Lifecycle:
 *  1. init()      → fetch session, init SDK, bind component (the native submit button stays for other methods)
 *  2. onSubmit    → create the Shopware order first (this empties the cart), then let the SDK pay
 *  3. onSuccess   → link the PAY. payment to that order, redirect to the finish page
 *  4. onError     → SDK shows its own error; if the order already exists, go to its edit page,
 *                   because with an empty cart the confirm page can't retry or switch method
 */
export default class PaynlPayPartsCardPlugin extends Plugin {

    static options = {
        /** POST /PaynlPayment/payparts/session */
        sessionUrl: '',
        /** POST /PaynlPayment/payparts/create-order */
        createOrderUrl: '',
        /** POST /PaynlPayment/payparts/link-transaction */
        linkTransactionUrl: '',
        /** POST /PaynlPayment/payparts/report-issue (log only) */
        reportIssueUrl: '',
        /** Billing country for the SDK: NL, BE, DE, or DEFAULT for any other country (SDK limitation) */
        country: 'NL',
        /** BCP 47 language subtag derived from the storefront locale */
        language: 'nl',
        /**
         * Edit-order retry mode — populated by the backend when payment is retried
         * on an existing order (empty string on first checkout).
         */
        orderId: '',
        editOrderUrl: '',
        /** Shopware UUID of the Pay.Parts card payment method resolved for this checkout */
        paymentMethodId: '',
        /** Translated UI strings (key → text), provided by the Twig template. */
        snippets: {},
    };

    init() {
        this._client             = new HttpClient();
        this._checkout           = null;
        this._sessionAmount      = {};
        this._orderRequest       = null;

        // Set by create-order (on the first Pay press, also in edit-order mode); later presses reuse it.
        this._orderTransactionId = null;
        this._editOrderUrl       = this.options.editOrderUrl || null;

        this.el.classList.add(LOADING_CLASS);
        this._fetchSessionAndMount();
    }

    // ─── Bootstrap ───────────────────────────────────────────────────────────

    /** POST to the backend to create a PAY.Parts session, then mount the SDK. */
    _fetchSessionAndMount() {
        // In edit-order mode, send the existing orderId so the session is built
        // from the order instead of the (empty) cart.
        const body = JSON.stringify(this.options.orderId
            ? { orderId: this.options.orderId }
            : { paymentMethodId: this.options.paymentMethodId });

        this._client.post(this.options.sessionUrl, body, (responseText, request) => {
            const data = this._parseResponse(responseText, request);
            if (!data?.sessionToken) {
                this._showError(this._t('sessionFailed'));
                return;
            }

            const { sessionToken, apiUrl, amountCents, currency } = data;
            // Sent back to create-order so the server can refuse an order whose total no longer matches.
            this._sessionAmount = { amountCents, currency };
            this._mountComponent(sessionToken, apiUrl).catch((e) => this._showError(e.message));
        });
    }

    /**
     * Initialises the PAY.Parts SDK, registers event handlers, then binds
     * the `card-payments` component to its dedicated DOM node.
     *
     * @param {string} sessionToken
     * @param {string} apiUrl
     * @returns {Promise<void>}
     */
    async _mountComponent(sessionToken, apiUrl) {
        await this._waitForSdk();

        this._checkout = await window.PayPartsSDK.init({
            sessionToken,
            apiUrl,
            country:   this.options.country,
            language:  this.options.language,
            // Default is 30s; after it the SDK continues without our order. Give order creation more room.
            onSubmitTimeout: 60000,
        });

        this._checkout.events = {
            onReady:           (event) => this._onReady(event),
            onSubmit:          (event) => this._onSubmit(event),
            onSuccess:         (event) => this._onSuccess(event),
            onError:           (event) => this._onError(event),
            onPartialPaid:     (event) => this._onPartialPaid(event),
            onInfoUpdated:     (event) => this._onInfoUpdated(event),
            onShippingUpdated: (event) => this._onShippingUpdated(event),
        };

        const cardComponent = this._checkout.prepare('card-payments');
        await cardComponent.bind(CARD_COMPONENT_SELECTOR);

        // bind() resolving guarantees the component is in the DOM.
        // Stop the spinner here so it always hides even if onReady never fires.
        this._stopLoading();
    }

    /**
     * The SDK is a <script type="module"> and may execute after this plugin initialises.
     * Polls for window.PayPartsSDK instead of failing immediately.
     *
     * @returns {Promise<void>}
     */
    async _waitForSdk() {
        for (let waited = 0; typeof window.PayPartsSDK === 'undefined'; waited += SDK_POLL_MS) {
            if (waited >= SDK_WAIT_MS) {
                throw new Error(this._t('sdkUnavailable'));
            }
            await new Promise((resolve) => setTimeout(resolve, SDK_POLL_MS));
        }
    }

    // ─── PAY.Parts event handlers ─────────────────────────────────────────────

    /** SDK is ready — clear any previous error and ensure loading state is gone. */
    _onReady() {
        this._clearError();
        this._stopLoading();
    }

    /**
     * User pressed "Pay" inside the SDK component.
     * Create the Shopware order first; resolve so PAY.Parts can continue with 3DS/auth.
     * Guard makes this idempotent — a retry uses the already-created order.
     */
    _onSubmit(event) {
        if (this._orderTransactionId !== null) {
            event.resolve();
            return;
        }

        // Repeated presses while the first request is in flight share it, so only one order is created.
        if (!this._orderRequest) {
            this._orderRequest = this._createOrder().finally(() => {
                this._orderRequest = null;
            });
        }

        this._clearError();
        this._orderRequest.then(() => event.resolve(), (error) => {
            // The SDK shows nothing for a rejected submit, so the customer needs our message.
            this._showError(error.message);
            event.reject(error);
        });
    }

    /** POST create-order once; stores the order transaction on success. @returns {Promise<void>} */
    _createOrder() {
        return new Promise((resolve, reject) => {
            const form = document.getElementById(CONFIRM_FORM_ID);

            // Same rules as the native "Submit order" button: the browser shows its bubble on e.g. the terms checkbox.
            if (form && !form.reportValidity()) {
                reject(new Error(this._t('requiredFields')));
                return;
            }

            this._client.post(
                this.options.createOrderUrl,
                JSON.stringify({
                    paymentMethodId: this.options.paymentMethodId,
                    ...(this.options.orderId ? { orderId: this.options.orderId } : {}),
                    ...this._sessionAmount,
                    ...this._collectOrderData(form),
                }),
                (responseText, request) => {
                    const data = this._parseResponse(responseText, request);
                    if (!data?.orderTransactionId) {
                        // Edit-order: switching to the card method can change the order total (surcharge), and the
                        // session was made with the old total. The order is already switched, so a reload
                        // creates a session with the right amount.
                        if (request.status === 409 && this.options.orderId) {
                            setTimeout(() => window.location.reload(), 3000);
                        }

                        reject(new Error(request.status === 409
                            ? this._t('cartChanged')
                            : this._t('orderFailed')));
                        return;
                    }

                    const { orderTransactionId, editOrderUrl } = data;
                    this._orderTransactionId = orderTransactionId;
                    this._editOrderUrl = editOrderUrl ?? this._editOrderUrl;
                    resolve();
                }
            );
        });
    }

    /**
     * Payment authorised by PAY.Parts.
     * Link the PAY.nl transaction to the Shopware order, then redirect to the
     * finish page (the exchange URL will handle final status updates).
     */
    _onSuccess(event) {
        const paynlTransactionId = event.orderId ?? '';

        // The SDK can continue after its own onSubmit timeout, so a payment may exist without an order.
        if (this._orderTransactionId === null) {
            event.resolve();
            this._reportIssue('payment-without-order', { paynlTransactionId });
            this._showError(this._t('orphanPayment', { reference: paynlTransactionId }));
            return Promise.resolve();
        }

        const body = JSON.stringify({
            paynlTransactionId,
            orderTransactionId: this._orderTransactionId,
        });

        return new Promise((resolve) => {
            this._client.post(this.options.linkTransactionUrl, body, (responseText, request) => {
                event.resolve(); // always resolve so the SDK can clean up

                const data = this._parseResponse(responseText, request);
                if (!data?.redirectUrl) {
                    // The card is already charged: never send the customer to the retry page (double charge).
                    this._reportIssue('link-failed', {
                        paynlTransactionId,
                        orderTransactionId: this._orderTransactionId,
                        status: request.status,
                    });
                    this._showError(this._t('orphanPayment', { reference: paynlTransactionId }));
                    resolve();
                    return;
                }

                this._redirectToUrl(data.redirectUrl);
                resolve();
            });
        });
    }

    /**
     * Payment failed — the SDK renders its own error inside #paynl-card-payments.
     * If our order already exists, the cart is empty, so the confirm page can no longer switch
     * payment method: send the customer to the order's edit page (Shopware's standard place to
     * retry a failed payment with the card widget or any other method). The short delay lets
     * the SDK error be read first.
     */
    _onError(event) {
        event.resolve();

        if (this._orderTransactionId !== null) {
            setTimeout(() => this._redirectToEditOrder(), 2000);
        }
    }

    /**
     * Only part of the amount was paid (e.g. a gift card). The order cannot be completed from here,
     * so tell the customer instead of linking an incomplete payment. Amounts are in minor units.
     */
    _onPartialPaid(event) {
        const remaining = `${((event.amountRemaining ?? 0) / 100).toFixed(2)} ${event.currency ?? ''}`.trim();
        this._reportIssue('partial-payment', {
            paynlTransactionId: event.orderId ?? event.transactionId ?? '',
            amountRemaining: event.amountRemaining,
        });
        this._showError(this._t('partialPaid', { remaining }));
        event.resolve?.();
    }

    /** Fired when customer info is updated inside the SDK — no action needed for card-only. */
    _onInfoUpdated() {}

    /** Fired when shipping is changed inside the SDK — no action needed for card-only. */
    _onShippingUpdated() {}

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /** Confirm-form fields Shopware needs to create the order (terms, withdrawal, comment, codes). */
    _collectOrderData(form) {
        if (!form) {
            return {};
        }

        const formData = new FormData(form);

        return ORDER_FIELDS.reduce((data, name) => {
            const value = formData.get(name);
            if (typeof value === 'string' && value !== '') {
                data[name] = value;
            }

            return data;
        }, {});
    }

    /**
     * JSON body of a successful response, or null. Shopware's HttpClient also calls back on network
     * errors (status 0, empty body) and after a logout (HTML login page): never let JSON.parse throw
     * inside a callback, or the waiting promise/SDK event would never settle.
     */
    _parseResponse(responseText, request) {
        if (request.status === 0 || request.status >= 400) {
            return null;
        }

        try {
            const data = JSON.parse(responseText);
            return data && typeof data === 'object' ? data : null;
        } catch (e) {
            return null;
        }
    }

    /** Fire-and-forget: lets the server log a payment that needs manual follow-up. */
    _reportIssue(type, details) {
        this._client.post(
            this.options.reportIssueUrl,
            JSON.stringify({ type, orderId: this.options.orderId, ...details }),
            () => {}
        );
    }

    /** Translated string with %placeholder% replacement; falls back to the key. */
    _t(key, params = {}) {
        let text = this.options.snippets[key] ?? key;
        Object.entries(params).forEach(([name, value]) => {
            text = text.replace(`%${name}%`, value);
        });

        return text;
    }

    /** Redirect to the backend-generated edit-order URL when an order exists. */
    _redirectToEditOrder() {
        if (!this._editOrderUrl) {
            this._showError(this._t('orphanNoUrl'));
            return;
        }

        this._redirectToUrl(this._editOrderUrl);
    }

    /**
     * Follow a redirect URL after validating it is on the same origin.
     *
     * @param {string} redirectUrl
     */
    _redirectToUrl(redirectUrl) {
        try {
            const parsed = new URL(redirectUrl, window.location.origin);
            if (parsed.origin !== window.location.origin) {
                this._showError(this._t('redirectUnexpected'));
                return;
            }
            window.location.href = parsed.href;
        } catch (e) {
            this._showError(this._t('redirectInvalid'));
        }
    }

    /** Remove the loading spinner and fade the card form in. Idempotent — safe to call multiple times. */
    _stopLoading() {
        this.el.classList.remove(LOADING_CLASS);

        const cardEl = this.el.querySelector(CARD_COMPONENT_SELECTOR);
        if (cardEl) {
            cardEl.classList.add(READY_CLASS);
        }
    }

    /** Show the inline error message and always stop the loading spinner first. */
    _showError(message) {
        this._stopLoading();

        const errorEl = this.el.querySelector(`.${ERROR_CLASS}`);
        if (errorEl) {
            errorEl.textContent = message;
            errorEl.classList.remove('d-none');
        }
    }

    _clearError() {
        const errorEl = this.el.querySelector(`.${ERROR_CLASS}`);
        if (errorEl) {
            errorEl.classList.add('d-none');
            errorEl.textContent = '';
        }
    }
}
