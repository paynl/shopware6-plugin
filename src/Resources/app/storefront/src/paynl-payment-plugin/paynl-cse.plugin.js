import Plugin from 'src/plugin-system/plugin.class';
import StoreApiClient from 'src/service/store-api-client.service';
import PseudoModalUtil from 'src/utility/modal-extension/pseudo-modal.util';
import DomAccess from 'src/helper/dom-access.helper';
import FormSerializeUtil from 'src/utility/form/form-serialize.util';
import ElementLoadingIndicatorUtil from 'src/utility/loading-indicator/element-loading-indicator.util';
import {EncryptedForm, Elements, Events, PaymentCompleteModal, ErrorModal} from '../cse/pay-cryptography.amd';

export default class PaynlCsePlugin extends Plugin {
    init() {
        if (typeof paynlCheckoutOptions === 'undefined') {
            return;
        }

        this.paymentModalContent = '';
        this.finishUrl = '';
        this.modal = new PseudoModalUtil();
        this.orderForm = DomAccess.querySelector(document, '#confirmOrderForm');
        this._client = new StoreApiClient();

        let self = this;

        let el = document.querySelector('#changePaymentForm');
        el.setAttribute('data-pay-encrypt-form', '');

        if (!!paynlCheckoutOptions.orderId) {
            self.orderId = paynlCheckoutOptions.orderId;
        }

        let baseUrl = '/bundles/paynlpaymentshopware6';
        let publicEncryptionKeys = this.getPublicEncryptionKeys();

        this.encryptedForm = new EncryptedForm({
            'debug':                false,
            'public_keys':          publicEncryptionKeys,
            'language':             'NL',
            'post_url':             paynlCheckoutOptions.csePostUrl,
            'status_url':           paynlCheckoutOptions.cseStatusUrl + '?transactionId=%transaction_id%',
            'authorization_url':    paynlCheckoutOptions.cseAuthorizationUrl,
            'authentication_url':   paynlCheckoutOptions.cseAuthenticationUrl,
            'payment_complete_url': '',
            'refresh_url':          paynlCheckoutOptions.cseRefreshUrl,
            'form_input_payload_name': 'pay_encrypted_data',
            'form_selector': 'data-pay-encrypt-form', // attribute to look for to identify the target form
            'field_selector': 'data-pay-encrypt-field', // attribute to look for to identify the target form elements
            'field_value_reader': 'name', // grabs the required data keys from this attribute*/
            'bind': {
                'submit': false
            },
            'icons': {
                'creditcard': {
                    'default': baseUrl + '/logos/creditcard/cc-front.svg',
                    'alipay': baseUrl + '/logos/creditcard/cc-alipay.svg',
                    'american-express': baseUrl + '/logos/creditcard/cc-amex.svg',
                    'diners-club': baseUrl + '/logos/creditcard/cc-diners-club.svg',
                    'discover': baseUrl + '/logos/creditcard/cc-discover.svg',
                    'elo': baseUrl + '/logos/creditcard/cc-elo.svg',
                    'hiper': baseUrl + '/logos/creditcard/cc-hiper.svg',
                    'hipercard': baseUrl + '/logos/creditcard/cc-hipercard.svg',
                    'jcb': baseUrl + '/logos/creditcard/cc-jcb.svg',
                    'maestro': baseUrl + '/logos/creditcard/cc-maestro.svg',
                    'mastercard': baseUrl + '/logos/creditcard/cc-mastercard.svg',
                    'mir': baseUrl + '/logos/creditcard/cc-mir.svg',
                    'unionpay': baseUrl + '/logos/creditcard/cc-unionpay.svg',
                    'visa': baseUrl + '/logos/creditcard/cc-visa.svg'
                },
                'cvc': baseUrl + '/logos/creditcard/cc-back.svg',
            }
        });

        let eventDispatcher = this.encryptedForm.getEventDispatcher();

        this.encryptedForm.init();

        let payEncryptedDataInput = DomAccess.querySelector(document, 'input[name="pay_encrypted_data"]');
        payEncryptedDataInput.setAttribute('form', 'confirmOrderForm');

        document.getElementById('csePlaceOrder').addEventListener('click', this.placeOrder.bind(this));

        eventDispatcher.addListener(Events.onModalOpenEvent, function(event) {
            let eventSubject = event.getSubject()
            self.payDebug('onModalOpenEvent-Custom');
            event.stopPropagation();
            self.paymentModalContent = '';

            if (self.modal !== null) {
                self.payDebug('Closing modal');
                self.payDebug(self.modal);
                self.modal.close();
            }

            if (event.subject instanceof PaymentCompleteModal) {
                self.payDebug('instanceof PaymentCompleteModal');
                self.stopLoader();
                // TODO should redirect to finish page

                return;
            }

            if (event.subject instanceof ErrorModal) {
                self.payDebug('ErrorModal');

                let paymentErrorModalContent = event.getSubject().render();
                self.modal.updateContent(paymentErrorModalContent);
                self.modal.open();

                return;
            }

            if (eventSubject != null) {
                self.modal.updateContent(eventSubject.render());
                self.modal.open();
            }

            self.payDebug('showing modal');
            self.stopLoader();
        }, 10);

        eventDispatcher.addListener(Events.onModalCloseEvent, function (event) {
            event.stopPropagation();

            self.payDebug('onModalCloseEvent. Hiding activeModal');

            if (self.modal !== null) {
                self.payDebug('Closing modal');
                self.payDebug(self.modal);
                self.modal.close();
            }
        }, 10);

        eventDispatcher.addListener(Events.onPaymentCompleteEvent, function (event) {
            self.payDebug('onPaymentCompleteEvent custom');
            let pol = self.encryptedForm.getPoller();
            pol.clear();
            self.payDebug('Update redirection_url');
            event.setParameter('redirection_url',self.finishUrl.toString());
        }, 10);

        eventDispatcher.addListener(Events.onPaymentFailedEvent, function (event) {
            self.payDebug('onPaymentFailedEvent');
            //TODO should show the error
        }, 10);

        eventDispatcher.addListener(Events.onStateChangeEvent, function (event)  {
            /* Skip this function if the current event does not change the loading state. */
            if (event.hasParameter('state') && 'loading' in event.getParameter('state')) {
                event.getCurrentState().isLoading() ? self.startLoader() : self.stopLoader();
            }

            if (event.getCurrentState().isFormReadyForSubmission()) {
                if (self.orderId) {
                    self.encryptedForm.setPaymentPostUrl(paynlCheckoutOptions.csePostUrl + '?orderId=' + self.orderId);
                }
            }
        }, 100);
    }

    placeOrder(event) {
        event.preventDefault();

        let self = this;

        if (!this.orderForm.reportValidity()) {
            return;
        }

        const form =  DomAccess.querySelector(document, '#confirmOrderForm');
        this.startLoader();
        const formData = FormSerializeUtil.serialize(form);

        this.confirmOrder(formData);

        return false;
    }

    confirmOrder(formData) {
        const orderId = paynlCheckoutOptions.orderId;
        if (!!orderId) { //Only used if the order is being edited
            this.afterCreateOrder(JSON.stringify({id: paynlCheckoutOptions.orderId}));
            return;
        }

        this._client.post(paynlCheckoutOptions.checkoutOrderUrl, formData, this.afterCreateOrder.bind(this));
    }

    afterCreateOrder(response) {
        let order;
        try {
            order = JSON.parse(response);
            this.payDebug(order);
        } catch (error) {
            this.stopLoader();
            console.log('Error: invalid response from Shopware API', response);
            return;
        }

        this.orderId = order.id;
        this.finishUrl = new URL(
            location.origin + paynlCheckoutOptions.paymentFinishUrl);
        this.finishUrl.searchParams.set('orderId', order.id);
        this.errorUrl = new URL(
            location.origin + paynlCheckoutOptions.paymentErrorUrl);
        this.errorUrl.searchParams.set('orderId', order.id);
        let params = {
            'orderId': this.orderId,
            'finishUrl': this.finishUrl.toString(),
            'errorUrl': this.errorUrl.toString(),
        };

        paynlCheckoutOptions.orderId = this.orderId;

        this.encryptedForm.handleFormSubmission(
            this.encryptedForm.state.getElementFromReference(Elements.form)
        );

        // this._client.post(
        //     paynlCheckoutOptions.paymentHandleUrl,
        //     JSON.stringify(params),
        //     this.afterPayOrder.bind(this, this.orderId),
        // );
    }

    afterPayOrder(orderId, response) {
        try {
            response = JSON.parse(response);
            this.returnUrl = response.redirectUrl;
            this.payDebug(response);
        } catch (e) {
            this.startLoader();
            console.log('Error: invalid response from Shopware API', response);
        }
    }

    getPublicEncryptionKeys() {
        return JSON.parse(paynlCheckoutOptions.publicEncryptionKeys);
    }

    payDebug(text) {
        if (typeof text == 'string') {
            console.log('PAY. - ' + text);
        } else {
            console.log(text);
        }
    }

    startLoader() {
        ElementLoadingIndicatorUtil.create(document.body);
    }

    stopLoader() {
        ElementLoadingIndicatorUtil.remove(document.body);
    }
}
