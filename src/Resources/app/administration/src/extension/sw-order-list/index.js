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
            let isUserAllowedToRefund = this.acl.can('order_refund.creator');

            let statusAllowedForRefund = ['paid', 'paid_partially', 'refunded_partially'];
            let isStatusAllowedForRefund = statusAllowedForRefund.includes(statusName);

            return !(isUserAllowedToRefund && isStatusAllowedForRefund);
        }
    }
});
