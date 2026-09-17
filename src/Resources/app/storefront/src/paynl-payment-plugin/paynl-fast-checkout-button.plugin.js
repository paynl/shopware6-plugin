import Plugin from 'src/plugin-system/plugin.class';
import HttpClient from 'src/service/http-client.service';

export default class PaynlFastCheckoutButton extends Plugin {
    init() {
        this.client = new HttpClient();
        
        const mainButton = document.getElementById('btn-fast-checkout');
        if (mainButton) {
            mainButton.addEventListener('click', this.onFastCheckoutClick.bind(this));
        }

        const modalButton = document.querySelector('.btn-fast-checkout-modal');
        if (modalButton) {
            modalButton.addEventListener('click', this.onModalCheckoutClick.bind(this));
        }

        const backdrop = document.querySelector('.paynl-ideal-modal-backdrop');
        if (backdrop) {
            backdrop.addEventListener('click', this.onCloseModalClick.bind(this));
        }
    }

    onFastCheckoutClick(event) {
        event.preventDefault();
        
        const expressCheckoutModalEnabled = this.el.dataset.paynlFastCheckoutButton === 'true';
        
        if (expressCheckoutModalEnabled) {
            this.showModal();
        } else {
            this.startPayment(event.currentTarget);
        }
    }

    onModalCheckoutClick(event) {
        event.preventDefault();
        this.startPayment(event.currentTarget);
    }

    startPayment(button) {
        const url = button.dataset.startPaymentUrl;

        if (!url) {
            console.error('Missing payment URL');
            return;
        }

        button.classList.add('is-loading');
        button.setAttribute('disabled', 'disabled');

        this.client.post(url, new FormData(), (responseText) => {
            try {
                const data = JSON.parse(responseText);
                window.location.href = data.redirectUrl;
            } catch (e) {
                window.location.reload();
            }
        }, 'text', false, (xhr) => {
            button.classList.remove('is-loading');
            button.removeAttribute('disabled');

            try {
                const data = JSON.parse(xhr.responseText);
                if (data.redirectUrl) {
                    window.location.href = data.redirectUrl;
                    return;
                }
            } catch (e) {
                // fall through to reload
            }

            window.location.reload();
        });
    }

    showModal() {
        const modal = document.querySelector('.paynl-ideal-modal');
        const backdrop = document.querySelector('.paynl-ideal-modal-backdrop');
        
        if (modal && backdrop) {
            modal.classList.add('visible');
            backdrop.classList.add('visible');
            document.body.style.overflow = 'hidden';
        }
    }

    onCloseModalClick(event) {
        event.preventDefault();

        const modal = document.querySelector('.paynl-ideal-modal');
        const backdrop = document.querySelector('.paynl-ideal-modal-backdrop');
        
        if (modal && backdrop) {
            modal.classList.remove('visible');
            backdrop.classList.remove('visible');
            document.body.style.overflow = '';
        }
    }
}
