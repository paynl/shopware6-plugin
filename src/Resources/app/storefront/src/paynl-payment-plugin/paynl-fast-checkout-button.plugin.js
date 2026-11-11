import Plugin from '../plugin-system/plugin.class';
import HttpClient from '../service/http-client.service';

export default class PaynlFastCheckoutButton extends Plugin {
    init() {
        this.client = new HttpClient();
        this.el.addEventListener('click', this.onFastCheckoutClick.bind(this));
        this.bindModalOnce();
    }

    // The modal is rendered once in the base layout, so listen once for all instances.
    bindModalOnce() {
        if (PaynlFastCheckoutButton.modalBound) {
            return;
        }
        PaynlFastCheckoutButton.modalBound = true;

        // Bootstrap's offcanvas pulls focus back inside itself; keep focus events in the modal from reaching it.
        document.querySelector('.paynl-ideal-modal')?.addEventListener('focusin', (event) => event.stopPropagation());

        // Capture phase + stopPropagation: Esc must close only the modal, not the mini cart behind it.
        document.addEventListener('keydown', (event) => {
            const modal = document.querySelector('.paynl-ideal-modal.visible');
            if (!modal) {
                return;
            }

            if (event.key === 'Escape') {
                event.stopPropagation();
                this.toggleModal(false);
            } else if (event.key === 'Tab') {
                // Keep Tab inside the modal.
                const items = modal.querySelectorAll('a[href], button');
                const [first, last] = [items[0], items[items.length - 1]];
                const goingOut = event.shiftKey ? document.activeElement === first : document.activeElement === last;
                if (goingOut || !modal.contains(document.activeElement)) {
                    event.preventDefault();
                    (event.shiftKey ? last : first).focus();
                }
            }
        }, true);

        document.addEventListener('click', (event) => {
            const checkoutButton = event.target.closest('.btn-fast-checkout-modal');
            if (checkoutButton) {
                event.preventDefault();
                this.startPayment(checkoutButton);
            } else if (event.target.closest('.paynl-ideal-modal-backdrop')) {
                this.toggleModal(false);
            }
        });
    }

    onFastCheckoutClick(event) {
        event.preventDefault();

        if (this.el.dataset.paynlFastCheckoutButton === 'true') {
            this.toggleModal(true, this.el);
        } else {
            this.startPayment(this.el);
        }
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

    toggleModal(show, trigger) {
        const modal = document.querySelector('.paynl-ideal-modal');
        if (!modal || modal.classList.contains('visible') === show) {
            return;
        }

        modal.classList.toggle('visible', show);
        document.querySelector('.paynl-ideal-modal-backdrop')?.classList.toggle('visible', show);

        if (show) {
            // Remember what to restore: the mini cart already locks scroll itself.
            PaynlFastCheckoutButton.previousOverflow = document.body.style.overflow;
            PaynlFastCheckoutButton.trigger = trigger;
            document.body.style.overflow = 'hidden';
            // The modal is still visibility:hidden until its fade-in has started, so focus() would be ignored now.
            modal.addEventListener('transitionend', () => modal.querySelector('.btn-fast-checkout-modal')?.focus(), { once: true });
        } else {
            document.body.style.overflow = PaynlFastCheckoutButton.previousOverflow || '';
            PaynlFastCheckoutButton.trigger?.focus();
        }
    }
}
