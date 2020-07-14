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
    }
}

export default PaynlPaymentPlugin;
