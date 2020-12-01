import Plugin from 'src/plugin-system/plugin.class';

class PaynlPaymentPlugin extends Plugin {
    init() {
        this.paymentMethodsScriptsInit();
    }

    paymentMethodsScriptsInit() {
        const paymentMethodsRadio = document.getElementsByClassName('payment-method-input');
        for (let i = 0; i < paymentMethodsRadio.length; i++) {
            paymentMethodsRadio[i].onchange = this.onChangeCallback;
        }

        const form = document.getElementById('confirmPaymentForm');
        form.addEventListener('submit', this.onSavePaymentMethod);

        const phoneInput = form.querySelector('.paynl-phone');
        if (phoneInput !== null) {
            phoneInput.addEventListener('focus', this.onInputFocus);
        }
    }

    onSavePaymentMethod(element) {
        const data = {};
        const currentPaymentMethod = document.getElementsByClassName('paynl-payment-method-block active')[0];

        if (currentPaymentMethod.querySelector('#paynl-ideal-banks-select') !== null) {
            const idealBankSelect = document.getElementById('paynl-ideal-banks-select');

            if (idealBankSelect.value !== '') {
                data.issuer = idealBankSelect.value;
            } else {
                element.preventDefault();
                element.stopPropagation();

                idealBankSelect.classList.add('invalid');

                return;
            }
        }

        if (currentPaymentMethod.querySelector('.paynl-paylater-fields')) {
            const dobInput = currentPaymentMethod.querySelector('.paynl-dob');
            if (dobInput && dobInput.value !== '') {
                data.dob = dobInput.value;
            }

            const phoneInput = currentPaymentMethod.querySelector('.paynl-phone');
            if (phoneInput && phoneInput.value !== '') {
                const regex = /^[\+]?[(]?[0-9]{3}[)]?[-\s\.]?[0-9]{3}[-\s\.]?[0-9]{4,6}$/im;
                if (!regex.test(phoneInput.value)) {
                    element.preventDefault();
                    element.stopPropagation();

                    phoneInput.classList.add('invalid');

                    return;
                }

                data.phone = phoneInput.value;
            }
        }

        const xhr = new XMLHttpRequest();
        xhr.open('POST', '/PaynlPayment/order/change/paylater-fields', true);
        xhr.setRequestHeader('Content-Type', 'application/json');
        xhr.send(JSON.stringify(data));
    }

    onChangeCallback(element) {
        const paymentBlocks = document.getElementsByClassName('paynl-payment-method-block');
        for (let i = 0; i < paymentBlocks.length; i++) {
            paymentBlocks[i].classList.remove('active');
        }
        const currentPaymentBlock = element.target.parentNode;
        currentPaymentBlock.classList.add('active');

        if (currentPaymentBlock.querySelector('#paynl-ideal-banks-select') !== null) {
            const idealBankSelect = document.getElementById('paynl-ideal-banks-select');

            idealBankSelect.value = '';
            idealBankSelect.classList.remove('invalid');
        }
    }

    onInputFocus(event) {
        event.target.classList.remove('invalid');
    }
}

export default PaynlPaymentPlugin;
