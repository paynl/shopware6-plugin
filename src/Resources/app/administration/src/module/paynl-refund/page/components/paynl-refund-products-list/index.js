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

    data() {
       return {
           restockAll: false
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

        productRestockChanged() {
            let checked = 0;
            for (let product of this.products) {
                if (product.rstk) {
                    checked += 1;
                }
            }

            this.restockAll = checked === this.products.length;
        },

        onRestockAllChange(value) {
            for (let product of this.products) {
                product.rstk = value;
            }

            this.restockAll = value;
        }
    }
});
