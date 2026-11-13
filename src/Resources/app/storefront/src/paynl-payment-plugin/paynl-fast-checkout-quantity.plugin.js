import Plugin from '../plugin-system/plugin.class';
import HttpClient from '../service/http-client.service';

export default class PaynlFastCheckoutQuantity extends Plugin {
    init() {
        this.client = new HttpClient();

        // Scope to this.el: #btn-fast-checkout is not unique (mini cart / other buy widgets reuse it).
        const root = this.el.closest('form') || this.el.closest('.product-detail-buy') || document;

        root.querySelectorAll('.js-quantity-selector, .product-detail-quantity-select').forEach((input) => {
            input.addEventListener('change', this.onProductQuantityChange.bind(this));
        });

        this.el.addEventListener('click', this.onProductCheckoutClick.bind(this));
    }

    onProductQuantityChange(event) {
        this.el.dataset.quantity = event.target.value;
    }

    onProductCheckoutClick(event) {
        const button = event.currentTarget;
        const productId = button.dataset.productId;
        const quantity = button.dataset.quantity || '1';
        const url = button.dataset.startProductPaymentUrl;

        // If this is NOT a product page, skip
        if (!productId || !url) {
            return;
        }
        event.preventDefault();
        event.stopPropagation();

        const formData = new FormData();
        formData.append('productId', productId);
        formData.append('quantity', quantity);

        button.classList.add('is-loading');
        button.setAttribute('disabled', 'disabled');

        this.client.post(url, formData, (responseText) => {
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
}
