const { Component, Context } = Shopware;
import template from './sw-order-general-info.html.twig';
import formatPaymentMethodLabel from '../../util/payment-method-label.util';
import VersionCompare from '../../util/version-compare.util';

Component.override('sw-order-general-info', {
    template,

    inject: ['PaynlPaymentService'],

    computed: {
        isShopware67() {
            return new VersionCompare().isGreaterOrEqual(Context.app.config.version, '6.7');
        },

        issuerName() {
            const customFields = this.order.customFields || {};

            return customFields.paynlIssuerName || null;
        }
    },

    methods: {
        paymentMethodLabel(transaction) {
            return formatPaymentMethodLabel(transaction.paymentMethod.translated.distinguishableName, this.issuerName);
        },

        onLeaveModalConfirm(docIds, sendMail = true) {
            this.showModal = false;

            let transition = null;
            let currentActionName = this.currentActionName;

            switch (this.currentStateType) {
                case 'order_transaction':
                    transition = this.orderStateMachineService.transitionOrderTransactionState(
                        this.transaction.id,
                        this.currentActionName,
                        { documentIds: docIds, sendMail },
                    ).then(() => {
                        let transactionId = this.transaction.id;
                        this.PaynlPaymentService.changeTransactionStatus({
                            transactionId,
                            currentActionName
                        }).then(() => {
                            this.$emit('order-state-change');
                        }).catch((errorResponse) => {
                            this.createNotificationError({
                                title: this.$tc('sw-plugin-config.titleSaveError'),
                                message: errorResponse,
                            });
                        });
                    }).catch((error) => {
                        this.createStateChangeErrorNotification(error);
                    });
                    break;
                case 'order_delivery':
                    transition = this.orderStateMachineService.transitionOrderDeliveryState(
                        this.delivery.id,
                        this.currentActionName,
                        { documentIds: docIds, sendMail },
                    );
                    break;
                case 'order':
                    transition = this.orderStateMachineService.transitionOrderState(
                        this.order.id,
                        this.currentActionName,
                        { documentIds: docIds, sendMail },
                    );
                    break;
                default:
                    this.createNotificationError({
                        message: this.$tc('sw-order.stateCard.labelErrorStateChange'),
                    });
                    return;
            }

            if (transition) {
                transition.then(() => {
                    this.loadHistory();
                }).catch((error) => {
                    this.createStateChangeErrorNotification(error);
                });
            }

            this.currentActionName = null;
            this.currentStateType = null;
        }
    }
});
