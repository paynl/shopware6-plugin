const { Component } = Shopware;

Component.override('sw-order-state-history-card', {
    inject: ['PaynlPaymentService'],

    methods: {
        onLeaveModalConfirm(docIds, sendMail = true, internalComment = null) {
            this.showModal = false;
            const currentActionName = this.currentActionName;
            const transitionPayload = { documentIds: docIds, sendMail, internalComment };
            if (this.currentStateType === 'orderTransactionState') {

                this.orderStateMachineService.transitionOrderTransactionState(
                    this.transaction.id,
                    currentActionName,
                    transitionPayload,
                ).then(() => this.PaynlPaymentService.changeTransactionStatus({
                    transactionId: this.transaction.id,
                    currentActionName,
                })).then(() => {
                    this.$emit('order-state-change');
                    this.loadHistory();
                }).catch((errorResponse) => {
                    if (typeof errorResponse === 'string' || errorResponse?.message) {
                        this.createNotificationError({
                            title: this.$tc('sw-plugin-config.titleSaveError'),
                            message: errorResponse,
                        });
                        return;
                    }

                    this.createStateChangeErrorNotification(errorResponse);
                });
            } else if (this.currentStateType === 'orderState') {
                this.orderStateMachineService.transitionOrderState(
                    this.order.id,
                    this.currentActionName,
                    transitionPayload,
                ).then(() => {
                    this.$emit('order-state-change');
                    this.loadHistory();
                }).catch((error) => {
                    this.createStateChangeErrorNotification(error);
                });
            } else if (this.currentStateType === 'orderDeliveryState') {
                this.orderStateMachineService.transitionOrderDeliveryState(
                    this.delivery.id,
                    this.currentActionName,
                    transitionPayload,
                ).then(() => {
                    this.$emit('order-state-change');
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

