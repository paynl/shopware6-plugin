import template from './paynl-refund-single-order-product.html.twig';

const { Component } = Shopware;

Component.register('paynl-refund-single-order-product', {
    template,

    props: {
        product: {
            type: Object,
            required: true,
            default: null
        }
    },

    methods: {
        getProductQuantitySelect() {
            let options = [];
            for (let i = 0; i <= this.product.price.quantity; i++) {
                options.push({
                    "id": i,
                    "name": "" + i
                });
            }

            return options;
        },

        onProductRefundQuantityChange(selectedValue) {
            this.product.qnt = selectedValue;
            this.$emit('refund-quantity-changed', selectedValue);
        },

        onRestockChange(value) {
            this.product.rstk = value;
        }
    }
});
