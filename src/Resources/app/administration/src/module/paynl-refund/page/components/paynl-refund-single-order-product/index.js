import template from './paynl-refund-single-order-product.html.twig';
import VersionCompare from './../../../../../util/version-compare.util'

const { Component, Context } = Shopware;

Component.register('paynl-refund-single-order-product', {
    template,

    props: {
        product: {
            type: Object,
            required: true,
            default: null
        }
    },

    data() {
        return {
            productQuantity: this.product.price.quantity + 0,
            versionCompare: null,
        }
    },

    created() {
        this.versionCompare = new VersionCompare();
    },

    mounted() {
        this.product.qnt = this.product.price.quantity;
    },

    computed: {
        isShopware67() {
            return this.versionCompare.isGreaterOrEqual(Context.app.config.version, '6.7')
        },
    },

    methods: {
        getProductQuantitySelect() {
            let options = [];
            for (let i = 0; i <= this.product.price.quantity; i++) {
                options.push({
                    "value": i,
                    "label": "" + i
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
            this.$emit('product-restock-changed');
        }
    }
});
