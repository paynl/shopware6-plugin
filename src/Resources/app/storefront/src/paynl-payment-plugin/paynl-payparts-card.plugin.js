import Plugin from 'src/plugin-system/plugin.class';
import DomAccess from 'src/helper/dom-access.helper';
import HttpClient from 'src/service/http-client.service';

const SUBMIT_BUTTON_SELECTOR  = '#confirmOrderForm button[type=submit]';
const CARD_COMPONENT_SELECTOR = '#paynl-card-payments';
const ERROR_CLASS             = 'paynl-payparts-card-error';
const LOADING_CLASS           = 'is-loading';
const READY_CLASS             = 'is-ready';

/**
 * Shopware storefront plugin that mounts the PAY.Parts `card-payments` component.
 *
 * The Twig template renders this element only when paynlId == 11 is the
 * currently selected payment method, so no runtime selection-checking is needed.
 *
 * Lifecycle:
 *  1. init()      → hide native submit button, fetch session, init SDK, bind component
 *  2. onSubmit    → create Shopware order via AJAX, call event.resolve()
 *  3. onSuccess   → link PAY.Parts transaction to the order, redirect to finish page
 *  4. onError     → SDK renders its own error; resolve so the customer can retry
 */
export default class PaynlPayPartsCardPlugin extends Plugin {

    static options = {
        /** POST /PaynlPayment/payparts/session */
        sessionUrl: '',
        /** POST /PaynlPayment/payparts/create-order */
        createOrderUrl: '',
        /** POST /PaynlPayment/payparts/link-transaction */
        linkTransactionUrl: '',
        /** ISO 3166-1 alpha-2 country code from the customer's billing address */
        country: 'NL',
        /** BCP 47 language subtag derived from the storefront locale */
        language: 'nl',
        /** Enable SDK debug output — set true on staging, false on production */
        debugMode: false,
        /**
         * Edit-order retry mode — populated by the backend when payment is retried
         * on an existing order (empty string on first checkout).
         */
        orderId: '',
        orderTransactionId: '',
        editOrderUrl: '',
    };

    init() {
        this._client             = new HttpClient();
        this._checkout           = null;

        // Pre-populate from options when retrying payment on an existing order.
        // The idempotent guard in _onSubmit will skip create-order automatically.
        this._orderTransactionId = this.options.orderTransactionId || null;
        this._editOrderUrl       = this.options.editOrderUrl || null;

        this.el.classList.add(LOADING_CLASS);
        this._hideNativeSubmit();
        this._fetchSessionAndMount();
    }

    // ─── Bootstrap ───────────────────────────────────────────────────────────

    /** POST to the backend to create a PAY.Parts session, then mount the SDK. */
    _fetchSessionAndMount() {
        // In edit-order mode, send the existing orderId so the session is built
        // from the order instead of the (empty) cart.
        const body = this.options.orderId
            ? JSON.stringify({ orderId: this.options.orderId })
            : null;

        this._client.post(this.options.sessionUrl, body, (responseText, request) => {
            if (request.status >= 400) {
                this._showError('Session creation failed. Please reload and try again.');
                return;
            }

            // apiUrl distinguishes beta vs. production — determined server-side
            const { sessionToken, apiUrl } = JSON.parse(responseText);
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
        if (typeof window.PayPartsSDK === 'undefined') {
            throw new Error('PAY.Parts SDK is not available. Please reload the page.');
        }

        this._checkout = await window.PayPartsSDK.init({
            sessionToken,
            apiUrl,
            country:   this.options.country,
            language:  this.options.language,
            debugMode: this.options.debugMode,
        });

        this._checkout.events = {
            onReady:           (event) => this._onReady(event),
            onSubmit:          (event) => this._onSubmit(event),
            onSuccess:         (event) => this._onSuccess(event),
            onError:           (event) => this._onError(event),
            onInfoUpdated:     (event) => this._onInfoUpdated(event),
            onShippingUpdated: (event) => this._onShippingUpdated(event),
        };

        const cardComponent = this._checkout.prepare('card-payments');
        await cardComponent.bind(CARD_COMPONENT_SELECTOR);

        // bind() resolving guarantees the component is in the DOM.
        // Stop the spinner here so it always hides even if onReady never fires.
        this._stopLoading();
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

        this._client.post(this.options.createOrderUrl, null, (responseText, request) => {
            if (request.status >= 400) {
                event.reject(new Error('Order creation failed. Please try again.'));
                return;
            }
            const {orderTransactionId, editOrderUrl} = JSON.parse(responseText);
            this._orderTransactionId = orderTransactionId;
            this._editOrderUrl = editOrderUrl ?? null;
            event.resolve();
        });
    }

    /**
     * Payment authorised by PAY.Parts.
     * Link the PAY.nl transaction to the Shopware order, then redirect to the
     * finish page (the exchange URL will handle final status updates).
     */
    _onSuccess(event) {
        const paynlTransactionId = event.orderId ?? '';
        const body = JSON.stringify({
            paynlTransactionId,
            orderTransactionId: this._orderTransactionId,
        });

        return new Promise((resolve) => {
            this._client.post(this.options.linkTransactionUrl, body, (responseText, request) => {
                event.resolve(); // always resolve so the SDK can clean up

                if (request.status >= 400) {
                    this._redirectToEditOrder();
                    resolve();
                    return;
                }

                const { redirectUrl } = JSON.parse(responseText);
                this._redirectToUrl(redirectUrl);
                resolve();
            });
        });
    }

    /**
     * Payment failed — the SDK renders its own error inside #paynl-card-payments,
     * so no additional UI update is needed here.
     * Resolving keeps the SDK interactive so the customer can correct and retry.
     * The idempotent guard in _onSubmit ensures the Shopware order is not re-created
     * on the next "Pay" attempt.
     */
    _onError(event) {
        event.resolve();
    }

    /** Fired when customer info is updated inside the SDK — no action needed for card-only. */
    _onInfoUpdated() {}

    /** Fired when shipping is changed inside the SDK — no action needed for card-only. */
    _onShippingUpdated() {}

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /** Redirect to the backend-generated edit-order URL when an order exists. */
    _redirectToEditOrder() {
        if (!this._editOrderUrl) {
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
                this._showError('Unexpected redirect. Please contact support.');
                return;
            }
            window.location.href = parsed.href;
        } catch (e) {
            this._showError('Invalid redirect URL. Please contact support.');
        }
    }

    /** Hide the native Shopware "Confirm order" button; the SDK provides its own "Pay" button. */
    _hideNativeSubmit() {
        const btn = DomAccess.querySelector(document, SUBMIT_BUTTON_SELECTOR, false);
        if (btn) {
            btn.style.display = 'none';
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
