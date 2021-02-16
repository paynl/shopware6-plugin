import Plugin from 'src/plugin-system/plugin.class';

export default class PaynlPaymentPlugin extends Plugin {
    init() {
        this.paymentMethodsScriptsInit();
    }

    paymentMethodsScriptsInit() {
        const trigger = document.getElementById('paynl-payment-plugin');

        if (trigger) {
            jQuery('.paynl-dob').datepicker({
                autoclose: true,
                format: "dd-mm-yyyy",
                weekStart: 1 // Monday
            });

            const form = trigger.parentNode;
            form.addEventListener('submit', this.onSavePaymentMethod);
            form.addEventListener('change', this.onChangeCallback);
            form.addEventListener('focus', this.removeInvalid, true);
        }
    }

    onSavePaymentMethod(element) {
        const data = {};
        const invalid = [];
        const currentPaymentMethod = document.querySelector('.paynl-payment-method-extra.active');

        if (currentPaymentMethod.querySelector('.paynl-ideal-banks-select')) {
            const idealBankSelect = currentPaymentMethod.querySelector('.paynl-ideal-banks-select');

            if (idealBankSelect.value == '') {
                invalid.push(idealBankSelect);
            } else {
                data.issuer = idealBankSelect.value;
            }
        }

        if (currentPaymentMethod.querySelector('.paynl-dob')) {
            const dobInput = currentPaymentMethod.querySelector('input.paynl-dob[type="text"]');
            if (dobInput.value == '') {
                invalid.push(dobInput);
            } else {
                data.dob = dobInput.value;
            }
        }

        if (currentPaymentMethod.querySelector('.paynl-phone')) {
            const phoneInput = currentPaymentMethod.querySelector('.paynl-phone');
            if (phoneInput.value == '') {
                invalid.push(phoneInput);
            } else {
                data.phone = phoneInput.value;
            }
        }

        if (invalid.length) {
            invalid.map(function (el) {
                el.classList.add('invalid');
            });

            element.preventDefault();
            element.stopPropagation();

            return;
        }

        const xhr = new XMLHttpRequest();
        xhr.open('POST', '/PaynlPayment/order/change/paylater-fields', true);
        xhr.setRequestHeader('Content-Type', 'application/json');
        xhr.send(JSON.stringify(data));
    }

    onChangeCallback(event) {
        // check if event is triggered by payment method change
        if (event.target.classList.contains('payment-method-input')) {
            // hide previous extra data block
            const prevExtraDataBlock = event.currentTarget.querySelector('.paynl-payment-method-extra.active');
            if (prevExtraDataBlock) {
                prevExtraDataBlock.classList.remove('active');
            }

            const currentPaymentBlock = event.target.parentNode.parentNode.parentNode;
            const extraDataBlock = currentPaymentBlock.querySelector('.paynl-payment-method-extra');

            // set issuer value to initial position
            const issuerSelect = extraDataBlock.querySelector('.paynl-ideal-banks-select');
            if (issuerSelect) {
                issuerSelect.value = '';
            }

            // remove all invalid classes if exists
            const invalid = extraDataBlock.querySelectorAll('.invalid');
            if (invalid.length) {
                Object.keys(invalid).map(function (key) {
                    invalid[key].classList.remove('invalid');
                });
            }

            extraDataBlock.classList.add('active');
        }
    }

    removeInvalid(event) {
        if (event.target.classList.contains('paynl-phone') ||
            event.target.classList.contains('paynl-dob') ||
            event.target.classList.contains('paynl-ideal-banks-select')
        ) {
            event.target.classList.remove('invalid');
        }
    }
}
