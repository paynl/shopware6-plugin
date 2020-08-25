import Plugin from 'src/plugin-system/plugin.class';

class PaynlPaymentPlugin extends Plugin {
    init() {
        this.paymentMethodsScriptsInit();
        this.idealChangeBank();
        this.savePayLaterData();
    }

    paymentMethodsScriptsInit() {
        const paymentMethodsRadio = document.getElementsByClassName('payment-method-input');
        for (let i = 0; i < paymentMethodsRadio.length; i++) {
            paymentMethodsRadio[i].onchange = this.onChangeCallback;
        }
        const paynlChangePMButton = document.getElementsByClassName('paynl-change-payment-method');
        for (let i = 0; i < paynlChangePMButton.length; i++) {
            paynlChangePMButton[i].onclick = this.onSavePaymentMethod;
        }
    }

    idealChangeBank() {
        document.getElementById('paynl-ideal-banks-select').onchange = this.onChangeBank;
    }

    savePayLaterData() {
        document.getElementsByClassName('paynl-change-payment-method').onclick = this.onSavePaymentMethod;
    }

    onChangeBank() {
        const idealBank = document.getElementById('paynl-ideal-banks-select').value;
        const xhr = new XMLHttpRequest();
        const data = {'paynlIssuer': idealBank};
        xhr.open('POST', '/PaynlPayment/order/change/payment', true);
        xhr.setRequestHeader('Content-Type', 'application/json');
        xhr.send(JSON.stringify(data));
    }

    onSavePaymentMethod(element) {
        const dobArray = document.getElementsByClassName('paynl-dob');
        const phoneArray = document.getElementsByClassName('paynl-phone');
        const paymentMethodInputId = element.target.dataset.paynlPaymentMethodInputId;
        const paymentMethodInput = document.getElementById(paymentMethodInputId);

        let dob = '';
        let phone = '';
        const currentDobFieldName = 'dob[' + paymentMethodInput.value + ']';
        const currentPhoneFieldName = 'phone[' + paymentMethodInput.value + ']';
        if (currentDobFieldName in dobArray) {
            dob = dobArray[currentDobFieldName].value
        }
        if (currentPhoneFieldName in phoneArray) {
            phone = phoneArray[currentPhoneFieldName].value
        }

        const xhr = new XMLHttpRequest();
        const data = {'dob': dob, 'phone': phone};
        xhr.open('POST', '/PaynlPayment/order/change/payment', true);
        xhr.setRequestHeader('Content-Type', 'application/json');
        xhr.send(JSON.stringify(data));
    }

    onChangeCallback(element) {
        const btnsModalBlockId = element.target.dataset.paynlModalBtnsId;
        const paynlModalButtons = document.getElementById(btnsModalBlockId);
        const paynlBtnsModalBlocks = document.getElementsByClassName('paynl-modal-buttons');
        for (let i = 0; i < paynlBtnsModalBlocks.length; i++) {
            paynlBtnsModalBlocks[i].style.display = 'none';
        }
        const paylaterBlocks = document.getElementsByClassName('paynl-paylater-fields');
        for (let i = 0; i < paylaterBlocks.length; i++) {
            paylaterBlocks[i].style.display = 'none';
        }
        if (paynlModalButtons !== null) {
            paynlModalButtons.style.display = 'inline-block';

            const paymentControlElement = paynlModalButtons.closest('.payment-control');
            const paynlPaymentMethodBanksBlocks = document.getElementById('paynl-banks');
            const idealBanksBlock = paymentControlElement.getElementsByClassName('paynl-payment-method-banks')[0];
            const idealBanksSelect = document.getElementById('paynl-ideal-banks-select');
            const paylaterBlock = paymentControlElement.getElementsByClassName('paynl-paylater-fields')[0];
            paynlPaymentMethodBanksBlocks.style.display = 'none';
            if (idealBanksBlock !== undefined) {
                idealBanksBlock.style.display = 'inline-flex';
            } else {
                idealBanksSelect.selectedIndex = 0;
            }

            if (paylaterBlock !== undefined) {
                paylaterBlock.style.display = 'inline-block';
            }
        }

        const idealBankSelect = document.getElementById('paynl-ideal-banks-select');
        idealBankSelect.value = '';
        const changeEvent = new Event('change');
        idealBankSelect.dispatchEvent(changeEvent);
    }
}

export default PaynlPaymentPlugin;
