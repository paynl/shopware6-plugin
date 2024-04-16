import template from './paynl-refund-products-list.html.twig';

const { Component } = Shopware;

Component.register('paynl-refund-products-list', {
    template,

    props: {
        products: {
            type: Array,
            required: true,
            default: []
        }
    },
    methods: {
        refundQuantityChanged() {
            let amount = 0;
            for (let product of this.products) {
                if (product.qnt) {
                    amount += product.qnt * product.price.unitPrice;
                }
            }

            this.$emit('change-refund-amount', amount);
        },

        onRestockAllChange(value) {
            for (let product of this.products) {
                product.rstk = value;
            }
        }
    }
});
