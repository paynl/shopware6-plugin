import Plugin from 'src/plugin-system/plugin.class';

export default class PaynlPaymentPlugin extends Plugin {
    init() {
        this.paymentMethodsScriptsInit();
    }

    paymentMethodsScriptsInit() {
        const paymentMethodsRadio = document.getElementsByClassName('payment-method-input');
        for (let i = 0; i < paymentMethodsRadio.length; i++) {
            paymentMethodsRadio[i].addEventListener('change', this.onChangeCallback);
        }

        const form = document.getElementById('confirmPaymentForm');
        form.addEventListener('submit', this.onSavePaymentMethod);

        const phoneInput = form.querySelector('.paynl-phone');
        if (phoneInput !== null) {
            phoneInput.addEventListener('focus', this.onInputFocus);
        }

        const issuerSelect = form.querySelector('#paynl-ideal-banks-select');
        if (issuerSelect !== null) {
            issuerSelect.addEventListener('change', this.onIssuerChange);
        }
    }

    onSavePaymentMethod(element) {
        const data = {};
        const currentPaymentMethod = document.querySelector('.paynl-payment-method-extra.active');

        if (currentPaymentMethod.querySelector('#paynl-ideal-banks-select') !== null) {
            const idealBankSelect = currentPaymentMethod.querySelector('#paynl-ideal-banks-select');

            if (idealBankSelect.value !== '') {
                data.issuer = idealBankSelect.value;
            } else {
                element.preventDefault();
                element.stopPropagation();

                idealBankSelect.classList.add('invalid');

                return;
            }
        }

        if (currentPaymentMethod.querySelector('.paynl-dob')) {
            const dobInput = currentPaymentMethod.querySelector('.paynl-dob');
            if (dobInput && dobInput.value !== '') {
                data.dob = dobInput.value;
            }
        }
        if (currentPaymentMethod.querySelector('.paynl-phone')) {
            const phoneInput = currentPaymentMethod.querySelector('.paynl-phone');
            if (phoneInput && phoneInput.value !== '') {
                data.phone = phoneInput.value;
            }
        }

        const xhr = new XMLHttpRequest();
        xhr.open('POST', '/PaynlPayment/order/change/paylater-fields', true);
        xhr.setRequestHeader('Content-Type', 'application/json');
        xhr.send(JSON.stringify(data));
    }

    onChangeCallback(element) {
        const extraDataBlocks = document.getElementsByClassName('paynl-payment-method-extra');
        for (let i = 0; i < extraDataBlocks.length; i++) {
            extraDataBlocks[i].classList.remove('active');
        }
        const currentPaymentBlock = element.target.parentNode.parentNode.parentNode;
        const extraDataBlock = currentPaymentBlock.querySelector('.paynl-payment-method-extra');
        extraDataBlock.classList.add('active');

        if (currentPaymentBlock.querySelector('#paynl-ideal-banks-select') !== null) {
            const idealBankSelect = document.getElementById('paynl-ideal-banks-select');

            idealBankSelect.value = '';
            idealBankSelect.classList.remove('invalid');
        }
    }

    onInputFocus(event) {
        event.target.classList.remove('invalid');
    }

    onIssuerChange(event) {
        event.target.classList.remove('invalid');
    }
}
