import Plugin from 'src/plugin-system/plugin.class';

class PaynlPaymentPlugin extends Plugin {
    init() {
        this.paymentMethodsScriptsInit();
        this.idealChangeBank();
    }

    paymentMethodsScriptsInit() {
        const paymentMethodsRadio = document.getElementsByClassName('payment-method-input');
        for (let i = 0; i < paymentMethodsRadio.length; i++) {
            paymentMethodsRadio[i].onchange = this.onChangeCallback;
        }
    }

    idealChangeBank() {
        document.getElementById('paynl-ideal-banks-select').onchange = this.onChangeBank;
    }

    onChangeBank() {
        const idealBank = document.getElementById('paynl-ideal-banks-select').value;
        const xhr = new XMLHttpRequest();
        const data = {'paynlIssuer': idealBank};
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
        if (paynlModalButtons !== null) {
            paynlModalButtons.style.display = 'inline-block';

            const paymentControlElement = paynlModalButtons.closest('.payment-control');
            const paynlPaymentMethodBanksBlocks = document.getElementById('paynl-banks');
            const idealBanksBlock = paymentControlElement.getElementsByClassName('paynl-payment-method-banks')[0];
            const idealBanksSelect = document.getElementById('paynl-ideal-banks-select');
            paynlPaymentMethodBanksBlocks.style.display = 'none';
            if (idealBanksBlock !== undefined) {
                idealBanksBlock.style.display = 'inline-flex';
            } else {
                idealBanksSelect.selectedIndex = 0;
            }
        }

        const idealBankSelect = document.getElementById('paynl-ideal-banks-select');
        idealBankSelect.value = '';
        const changeEvent = new Event('change');
        idealBankSelect.dispatchEvent(changeEvent);
    }
}

export default PaynlPaymentPlugin;
