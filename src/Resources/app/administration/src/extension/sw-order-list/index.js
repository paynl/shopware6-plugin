import template from "./index.html.twig";

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

Component.override('sw-order-list', {
    template,

    inject: ['acl'],

    computed: {
        orderCriteria() {
            const criteria = this.$super('orderCriteria');
            criteria.addAssociation('paynlTransactions');

            return criteria;
        },
    },

    methods: {
        isPaynlOrderTransaction(item) {
            if (item.extensions.paynlTransactions.length > 0) {
                return true;
            }

            return false;
        },

        isPaynlTransactionAllowedForRefund(statusName) {
            if (!this.acl.can('order.editor')) {
                return true;
            }

            return !(statusName === "paid" || statusName === "paid_partially" || statusName === "refunded_partially");
        }
    }
});
