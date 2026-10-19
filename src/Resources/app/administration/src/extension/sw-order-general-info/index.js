const { Component, Store } = Shopware;

Component.override('sw-order-general-info', {
    inject: ['PaynlPaymentService'],
    methods: {
        onLeaveModalConfirm(docIds, sendMail = true, internalComment = null) {
            this.showModal = false;
            Store.get('swOrderDetail').setLoading(['states', true]);

            const currentActionName = this.currentActionName;
            const currentStateType = this.currentStateType;
            const transitionPayload = { documentIds: docIds, sendMail, internalComment };

            let transition = null;

            switch (currentStateType) {
                case 'order_transaction':
                    transition = this.orderStateMachineService
                        .transitionOrderTransactionState(
                            this.transaction.id,
                            currentActionName,
                            transitionPayload,
                        )
                        .then(() => this.PaynlPaymentService.changeTransactionStatus({
                            transactionId: this.transaction.id,
                            currentActionName,
                        }).then(() => {
                            this.$emit('order-state-change');
                        }).catch((errorResponse) => {
                            this.createNotificationError({
                                title: this.$tc('sw-plugin-config.titleSaveError'),
                                message: errorResponse,
                            });
                        }));
                    break;
                case 'order_delivery':
                    transition = this.orderStateMachineService.transitionOrderDeliveryState(
                        this.delivery.id,
                        currentActionName,
                        transitionPayload,
                    );
                    break;
                case 'order':
                    transition = this.orderStateMachineService.transitionOrderState(
                        this.order.id,
                        currentActionName,
                        transitionPayload,
                    );
                    break;
                default:
                    Store.get('swOrderDetail').setLoading(['states', false]);
                    this.createNotificationError({
                        message: this.$tc('sw-order.stateCard.labelErrorStateChange'),
                    });
                    return;
            }

            if (transition) {
                transition
                    .then(() => {
                        this.loadHistory();
                    })
                    .catch((error) => {
                        this.createStateChangeErrorNotification(error);
                    })
                    .finally(() => {
                        Store.get('swOrderDetail').setLoading(['states', false]);
                    });
            }

            this.currentActionName = null;
            this.currentStateType = null;
        },
    },
});
