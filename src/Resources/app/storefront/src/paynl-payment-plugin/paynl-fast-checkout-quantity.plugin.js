import Plugin from 'src/plugin-system/plugin.class';

export default class PaynlFastCheckoutQuantity extends Plugin {
    init() {
        const productQuantityInput = document.querySelector('.js-quantity-selector');
        if (productQuantityInput) {
            productQuantityInput.addEventListener('change', this.onProductQuantityChange.bind(this))
        }

        const productQuantitySelect = document.querySelector('.product-detail-quantity-select');
        if (productQuantitySelect) {
            productQuantitySelect.addEventListener('change', this.onProductQuantityChange.bind(this))
        }
    }

    onProductQuantityChange(event) {
        const fastCheckoutLink = document.getElementById('btn-fast-checkout');
        const newQuantity = event.target.value;
        const productId = fastCheckoutLink.dataset.productId;

        const currentHref = fastCheckoutLink.getAttribute('href');
        const url = new URL(currentHref, window.location.origin);
        url.searchParams.set('productId', productId);
        url.searchParams.set('quantity', newQuantity);

        // Set only the path + query (relative URL)
        fastCheckoutLink.setAttribute('href', url.pathname + url.search);
    }
}
