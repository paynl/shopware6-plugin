import Plugin from 'src/plugin-system/plugin.class';
import HttpClient from 'src/service/http-client.service';

export default class PaynlFastCheckoutQuantity extends Plugin {
    init() {
        this.client = new HttpClient();
        
        const productQuantityInput = document.querySelector('.js-quantity-selector');
        if (productQuantityInput) {
            productQuantityInput.addEventListener('change', this.onProductQuantityChange.bind(this));
        }

        const productQuantitySelect = document.querySelector('.product-detail-quantity-select');
        if (productQuantitySelect) {
            productQuantitySelect.addEventListener('change', this.onProductQuantityChange.bind(this));
        }

        const fastCheckoutLink = document.getElementById('btn-fast-checkout');
        if (fastCheckoutLink) {
            fastCheckoutLink.addEventListener('click', this.onProductCheckoutClick.bind(this));
        }
    }

    onProductQuantityChange(event) {
        const fastCheckoutLink = document.getElementById('btn-fast-checkout');
        const newQuantity = event.target.value;
        
        if (fastCheckoutLink) {
            fastCheckoutLink.dataset.quantity = newQuantity;
        }
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
