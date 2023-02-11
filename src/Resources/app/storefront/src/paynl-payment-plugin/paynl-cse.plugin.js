import Plugin from 'src/plugin-system/plugin.class';
import StoreApiClient from 'src/service/store-api-client.service';
import PseudoModalUtil from 'src/utility/modal-extension/pseudo-modal.util';
import DomAccess from 'src/helper/dom-access.helper';
import FormSerializeUtil from 'src/utility/form/form-serialize.util';
import ElementLoadingIndicatorUtil from 'src/utility/loading-indicator/element-loading-indicator.util';
import {EncryptedForm, Elements, Events, PaymentCompleteModal, ErrorModal, StateChangeEvent} from '../cse/pay-cryptography.amd';

export default class PaynlCsePlugin extends Plugin {
    init() {
        if (typeof paynlCheckoutOptions === 'undefined') {
            return;
        }

        this.paymentModalContent = '';
        this.finishUrl = '';
        this.transactionId = '';
        this.modal = new PseudoModalUtil('', false);
        this.modalClosedByPayCse = false;
        this.orderForm = DomAccess.querySelector(document, '#confirmOrderForm');
        this._client = new StoreApiClient();

        let self = this;

        let changePaymentForm = document.querySelector('#changePaymentForm');
        if (changePaymentForm) {
            changePaymentForm.setAttribute('data-pay-encrypt-form', '');
        }

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

        this.initDefaultSubmitButton();

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
                return;
            }

            if (event.subject instanceof ErrorModal) {
                self.payDebug('ErrorModal');

                let paymentErrorModalContent = event.getSubject().render();

                self.modal = new PseudoModalUtil(paymentErrorModalContent, false);
                self.modal.open();

                return;
            }

            if (eventSubject != null) {
                self.modal = new PseudoModalUtil(eventSubject.render(), false);
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
                self.modalClosedByPayCse = true;
                self.modal.close();
            }
        }, 10);

        eventDispatcher.addListener(Events.onPaymentCompleteEvent, function (event) {
            self.payDebug('onPaymentCompleteEvent custom');
            let pol = self.encryptedForm.getPoller();
            pol.clear();
            self.payDebug('Update redirection_url');
            event.setParameter('redirection_url',self.finishUrl.toString());

            self.startLoader();
            self.updatePaymentStatusFromPay().then(() => {});
        }, 10);

        eventDispatcher.addListener(Events.onPaymentFailedEvent, function (event) {
            self.payDebug('onPaymentFailedEvent custom');

            self.cancelPaymentTransaction().then(() => {
                setTimeout(() => self.redirectToFinishUrl(), 2000);
            });
        }, 10);

        eventDispatcher.addListener(Events.onPaymentCanceledEvent, function (event) {
            self.payDebug('onPaymentCanceledEvent custom');

            self.cancelPaymentTransaction().then(() => {
                self.redirectToFinishUrl();
            });
        }, 90);

        eventDispatcher.addListener(Events.onStateChangeEvent, function (event)  {
            if (event.getCurrentState().isFormReadyForSubmission()) {
                if (self.orderId) {
                    self.encryptedForm.setPaymentPostUrl(paynlCheckoutOptions.csePostUrl + '?orderId=' + self.orderId);
                }
            }
        }, 100);

        eventDispatcher.addListener(Events.onActionableResponseEvent, function (event) {
            self.payDebug('event.onActionableResponseEvent');
            let transaction = event.subject.data.transaction;
            let transactionId = transaction !== undefined ? transaction.transactionId : null;
            if (transactionId !== null) {
                let params = {
                    'orderId': self.orderId,
                    'finishUrl': self.finishUrl.toString(),
                    'errorUrl': self.errorUrl.toString(),
                    'paymentType': 'cse',
                    'transactionId': transactionId,
                };

                self.transactionId = transactionId;

                // Handle payment
                self._client.post(
                    paynlCheckoutOptions.paymentHandleUrl,
                    JSON.stringify(params),
                    self.afterPayOrder.bind(self, self.orderId),
                );
            }
        });

        $(document).on('hide.bs.modal', '.js-pseudo-modal', function (event) {
            if (self.modalClosedByPayCse) {
                return;
            }

            self.payDebug('hide.bs.modal');
            eventDispatcher.dispatch(new StateChangeEvent(event, {
                'state': {modalOpen: false, formSubmitted: false}
            }), Events.onStateChangeEvent);
            /* Making sure any content/polling from this content will stop working */
            self.paymentModalContent = '';
            self.modalClosedByPayCse = false;
            let isPolling = self.encryptedForm.state.isPolling();
            if (isPolling) {
                let pol = self.encryptedForm.getPoller();
                pol.clear();
            }

            self.cancelPaymentTransaction().then(() => {
                self.redirectToFinishUrl();
            });
        });
    }

    placeOrder(event) {
        event.preventDefault();

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
        let url = null;
        let callback = null;
        if (!!orderId) { //Only used if the order is being edited
            formData.set('orderId', orderId);
            url = paynlCheckoutOptions.updatePaymentUrl;
            callback = this.afterSetPayment.bind(this);
        } else {
            url = paynlCheckoutOptions.checkoutOrderUrl;
            callback = this.afterCreateOrder.bind(this);
        }

        this._client.post(url, formData, callback);
    }

    afterCreateOrder(response) {
        let order;
        try {
            order = JSON.parse(response);
            this.payDebug(order);
        } catch (error) {
            this.stopLoader();
            this.payDebug('Error: invalid response from Shopware API', response);
            return;
        }

        this.orderId = order.id;
        this.finishUrl = new URL(
            location.origin + paynlCheckoutOptions.paymentFinishUrl);
        this.finishUrl.searchParams.set('orderId', order.id);
        this.errorUrl = new URL(
            location.origin + paynlCheckoutOptions.paymentErrorUrl);
        this.errorUrl.searchParams.set('orderId', order.id);

        paynlCheckoutOptions.orderId = this.orderId;

        this.encryptedForm.handleFormSubmission(
            this.encryptedForm.state.getElementFromReference(Elements.form)
        );
    }

    afterSetPayment(response) {
        try {
            const responseObject = JSON.parse(response);
            if (responseObject.success) {
                this.afterCreateOrder(JSON.stringify({id: paynlCheckoutOptions.orderId}));
            }
        } catch (e) {
            ElementLoadingIndicatorUtil.remove(document.body);
            this.payDebug('Error: invalid response from Shopware API', response);
        }
    }

    afterPayOrder(orderId, response) {
        try {
            response = JSON.parse(response);
            this.returnUrl = response.redirectUrl;
            this.payDebug(response);
        } catch (e) {
            this.payDebug('Error: invalid response from Shopware API', response);
        }
    }

    updatePaymentStatusFromPay() {
        let transactionId = this.transactionId;
        if (!transactionId) {
            return;
        }

        let url = '/PaynlPayment/cse/updatePaymentStatusFromPay';
        let formData = new FormData();
        formData.set('transactionId', transactionId);

        return fetch(url, {
            'method': 'POST',
            'cache': 'no-cache',
            'redirect': 'follow',
            'body': formData
        });
    }

    cancelPaymentTransaction() {
        let transactionId = this.transactionId;
        if (!transactionId) {
            return;
        }

        let url = '/PaynlPayment/cse/cancel';
        let formData = new FormData();
        formData.set('transactionId', transactionId);

        this.startLoader();

        return fetch(url, {
            'method': 'POST',
            'cache': 'no-cache',
            'redirect': 'follow',
            'body': formData
        });
    }

    redirectToFinishUrl() {
        if (this.returnUrl) {
            location.href = this.returnUrl;
        }
    }

    getPublicEncryptionKeys() {
        return JSON.parse(paynlCheckoutOptions.publicEncryptionKeys);
    }

    payDebug(text) {
        if (paynlCheckoutOptions.debug === 'true') {
            if (typeof text == 'string') {
                console.log('PAY. - ' + text);
            } else {
                console.log(text);
            }
        }
    }

    startLoader() {
        ElementLoadingIndicatorUtil.create(document.body);
    }

    stopLoader() {
        ElementLoadingIndicatorUtil.remove(document.body);
    }

    initDefaultSubmitButton() {
        let confirmFormSubmit = document.getElementById('confirmFormSubmit');
        let confirmOrderForm = document.getElementById('confirmOrderForm')
        let visaPaymentMethodInput = document.querySelector('input[data-paynlid="706"]');
        if (!visaPaymentMethodInput) {
            return;
        }

        if (!visaPaymentMethodInput.checked) {
            return;
        }

        let paynlPaymentMethodCse = document.querySelector('.paynl-payment-method-cse');
        if (!paynlPaymentMethodCse) {
            return;
        }

        if (confirmOrderForm) {
            confirmOrderForm.style.display = 'none';
        }

        if (confirmFormSubmit) {
            confirmFormSubmit.style.display = 'none';
        }
    }
}
