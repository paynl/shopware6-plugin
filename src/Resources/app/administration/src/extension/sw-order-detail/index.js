import template from './sw-order-detail.html.twig';

const ALLOWED_REFUND_STATUSES = ['paid', 'paid_partially', 'refunded_partially'];

const { Component, Context } = Shopware;
const Criteria = Shopware.Data.Criteria;

Component.override('sw-order-detail', {
    template,

    inject: ['acl'],

    data() {
        return {
            isPAYPayment: false,
            isPAYTransactionRefundAllowed: true,
        };
    },

    watch: {
        orderId: {
            deep: true,
            handler() {
                if (!this.orderId) {
                    return;
                }

                const orderRepository = this.repositoryFactory.create('order');
                const orderCriteria = new Criteria(1, 1);
                orderCriteria.addAssociation('transactions');
                orderCriteria.addAssociation('paynlTransactions');
                orderCriteria
                    .getAssociation('transactions')
                    .addSorting(Criteria.sort('createdAt', 'DESC'))
                    .setLimit(1);

                orderRepository.get(this.orderId, Context.api, orderCriteria).then((order) => {
                    const transaction = order.extensions.paynlTransactions.last();

                    if (!transaction) {
                        return;
                    }

                    this.isPAYPayment = !!transaction;

                    const isUserAllowedToEditOrder = this.acl.can('order.editor');

                    const transactionStatusName = transaction.stateMachineState.technicalName;
                    const isStatusAllowedForRefund = ALLOWED_REFUND_STATUSES.includes(transactionStatusName);

                    this.isPAYTransactionRefundAllowed = !(isUserAllowedToEditOrder && isStatusAllowedForRefund);
                });
            },
            immediate: true,
        },
    },
});
