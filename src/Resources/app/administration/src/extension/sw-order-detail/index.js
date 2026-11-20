import template from './sw-order-detail.html.twig';
import { isRefundDisabled } from './../../util/refund-permission.util';

const { Component, Context } = Shopware;
const Criteria = Shopware.Data.Criteria;

Component.override('sw-order-detail', {
    template,

    inject: ['acl'],

    data() {
        return {
            isPAYPayment: false,
            isPAYTransactionRefundDisabled: true,
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

                    this.isPAYTransactionRefundDisabled = isRefundDisabled(
                        this.acl,
                        transaction.stateMachineState.technicalName
                    );
                });
            },
            immediate: true,
        },
    },
});
