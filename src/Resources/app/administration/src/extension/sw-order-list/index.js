import template from "./index.html.twig";
import { isRefundDisabled } from './../../util/refund-permission.util';

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

        isPaynlTransactionRefundDisabled(statusName) {
            return isRefundDisabled(this.acl, statusName);
        }
    }
});
