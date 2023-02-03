import Plugin from 'src/plugin-system/plugin.class';
import IMask from '../../node_modules/imask/dist/imask';
import DomAccess from 'src/helper/dom-access.helper';
import StoreApiClient from 'src/service/store-api-client.service';

export default class PaynlPaymentPlugin extends Plugin {
    init() {
        this._client = new StoreApiClient();

        this.paymentMethodsScriptsInit();
        this.paymentPinMessageInit();
    }

    paymentMethodsScriptsInit() {
        const trigger = document.getElementById('paynl-payment-plugin');

        if (trigger) {
            this.initDateOfBirthMask();

            const form = trigger.parentNode;
            form.addEventListener('submit', this.onSavePaymentMethod.bind(this));
            form.addEventListener('change', this.onChangeCallback);
            form.addEventListener('focus', this.removeInvalid, true);
        }
    }

    paymentPinMessageInit() {
        const PAYNL_PIN_PAYMENT_METHOD_ID = '1927';

        let formOrder = DomAccess.querySelector(document, '#confirmOrderForm');

        formOrder.addEventListener('submit', function () {
            let paymentMethod = DomAccess.querySelector(document, 'input[name="paymentMethodId"]:checked');
            let paynlPaymentMethodId = paymentMethod.dataset.paynlid;
            if (paynlPaymentMethodId === undefined) {
                return true;
            }

            //PIN payment method ID
            if (paynlPaymentMethodId === PAYNL_PIN_PAYMENT_METHOD_ID) {
                let paynlProcessMessage = DomAccess.querySelector(document, '.paynl-process-message');
                paynlProcessMessage.classList.remove('d-none');
            }

            return true;
        });
    }

    initDateOfBirthMask() {
        const elements = document.querySelectorAll('.paynl-dob');
        const iMaskMinDate = new Date();
        iMaskMinDate.setDate(iMaskMinDate.getDate() - 1);
        iMaskMinDate.setFullYear(iMaskMinDate.getFullYear() - 100);

        Object.keys(elements).forEach(function(key) {
            return IMask(elements[key], {
                mask: Date,  // enable date mask

                // other options are optional
                pattern: 'd-`m-`Y',  // Pattern mask with defined blocks, default is 'd{.}`m{.}`Y'
                // define date -> str convertion
                format: function (date) {
                    var day = date.getDate();
                    var month = date.getMonth() + 1;
                    var year = date.getFullYear();

                    if (day < 10) day = "0" + day;
                    if (month < 10) month = "0" + month;

                    return [day, month, year].join('-');
                },
                // define str -> date convertion
                parse: function (str) {
                    var dayMonthYear = str.split('-');
                    return new Date(dayMonthYear[2], dayMonthYear[1] - 1, dayMonthYear[0]);
                },

                // optional interval options
                min: iMaskMinDate,
                max: new Date(),  // defaults to `1900-01-01`

                // also Pattern options can be set
                lazy: true,

                // and other common options
                overwrite: true  // defaults to `false`
            });
        });
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
            const dateRegExp = /(0[1-9]|1[0-9]|2[0-9]|3[01])-(0[1-9]|1[012])-[0-9]{4}/;

            if (dateRegExp.test(dobInput.value) === false) {
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
            invalid.forEach(function (element) {
                element.classList.add('invalid');
            });

            element.preventDefault();
            element.stopPropagation();

            return;
        }

        this.savePayLaterFields(data);
    }

    savePayLaterFields(data) {
        this._client.post('/store-api/PaynlPayment/order/change/paylater-fields', JSON.stringify(data));
    }

    onChangeCallback(event) {
        // check if event is triggered by payment method change
        if (event.target.className.indexOf('payment-method-input') !== -1) {
            // hide previous extra data block
            const prevExtraDataBlock = event.currentTarget.querySelector('.paynl-payment-method-extra.active');
            if (prevExtraDataBlock) {
                prevExtraDataBlock.classList.remove('active');
            }

            const currentPaymentBlock = event.target.parentNode.parentNode.parentNode;
            const extraDataBlock = currentPaymentBlock.querySelector('.paynl-payment-method-extra');
            if (!extraDataBlock) {
                return;
            }

            const paymentMethodBankName = event.currentTarget.querySelector('#paymentMethodBankName');
            if (paymentMethodBankName) {
                paymentMethodBankName.innerHTML = '';
            }

            // remove all invalid classes if exists
            const invalid = extraDataBlock.querySelectorAll('.invalid');
            if (invalid.length) {
                Object.keys(invalid).forEach(function (key) {
                    return invalid[key].classList.remove('invalid');
                });
            }

            extraDataBlock.classList.add('active');
        }

        if (event.target.className.indexOf('paynl-dob') !== -1) {
            let paynlDateOfBirthInput = event.target;
            if (!paynlDateOfBirthInput.previousElementSibling) {
                return;
            }

            let datePickerDateOfBirth = paynlDateOfBirthInput.previousElementSibling;
            datePickerDateOfBirth.value = paynlDateOfBirthInput.value;
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
