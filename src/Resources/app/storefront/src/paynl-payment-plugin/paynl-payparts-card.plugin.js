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
 *  4. onError     → show inline error, call event.resolve() to allow retry
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
    };

    init() {
        this._client             = new HttpClient();
        this._checkout           = null;     // PAY.Parts Checkout instance
        this._orderTransactionId = null;     // stored in onSubmit, used in onSuccess

        // Show spinner immediately (wrapper ships with is-loading from Twig)
        this.el.classList.add(LOADING_CLASS);
        this._hideNativeSubmit();
        this._fetchSessionAndMount();
    }

    // ─── Bootstrap ───────────────────────────────────────────────────────────

    /** POST to the backend to create a PAY.Parts session, then mount the SDK. */
    _fetchSessionAndMount() {
        this._client.post(this.options.sessionUrl, null, (responseText, request) => {
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

        // init() returns the Checkout instance; language/country drive SDK localisation
        this._checkout = await window.PayPartsSDK.init({
            sessionToken,
            apiUrl,
            country:  this.options.country,
            language: this.options.language,
        });

        // Events must be assigned after init(), not inside the options object
        this._checkout.events = {
            onReady:   (event) => this._onReady(event),
            onSubmit:  (event) => this._onSubmit(event),
            onSuccess: (event) => this._onSuccess(event),
            onError:   (event) => this._onError(event),
        };

        // prepare() + bind() is the PAY.Parts API for mounting a component
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
     */
    _onSubmit(event) {
        return new Promise((resolve) => {
            this._client.post(this.options.createOrderUrl, null, (responseText, request) => {
                if (request.status >= 400) {
                    event.reject(new Error('Order creation failed. Please try again.'));
                    resolve();
                    return;
                }

                const { orderTransactionId } = JSON.parse(responseText);
                this._orderTransactionId = orderTransactionId; // needed in onSuccess
                event.resolve(); // tell the SDK the order is ready; proceed with payment
                resolve();
            });
        });
    }

    /**
     * Payment authorised by PAY.Parts.
     * Link the PAY.nl transaction to the Shopware order, then redirect to the
     * finish page (the exchange URL will handle final status updates).
     */
    _onSuccess(event) {
        const paynlTransactionId = event.transaction?.transactionId ?? '';
        const body = JSON.stringify({
            paynlTransactionId,
            orderTransactionId: this._orderTransactionId,
        });

        return new Promise((resolve) => {
            this._client.post(this.options.linkTransactionUrl, body, (responseText, request) => {
                event.resolve(); // always resolve so the SDK can clean up

                if (request.status >= 400) {
                    // Linking failed; reload so the customer can retry
                    window.location.reload();
                    resolve();
                    return;
                }

                const { redirectUrl } = JSON.parse(responseText);
                window.location.href = redirectUrl; // go to checkout finish page
                resolve();
            });
        });
    }

    /** Payment failed — display the SDK error message and let the user retry. */
    _onError(event) {
        const message = event.error?.message ?? 'Payment failed. Please try again.';
        this._showError(message);
        event.resolve(); // resolve so the SDK stays interactive for a retry
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

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
            cardEl.classList.add(READY_CLASS); // triggers CSS fade-in transition
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
