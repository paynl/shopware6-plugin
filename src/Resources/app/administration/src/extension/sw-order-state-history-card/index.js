const { Component } = Shopware;

Component.override('sw-order-state-history-card', {
    inject: ['PaynlPaymentService'],

    methods: {
        onLeaveModalConfirm(docIds, sendMail = true) {
            this.showModal = false;
            let currentActionName = this.currentActionName;
            if (this.currentStateType === 'orderTransactionState') {

                this.orderStateMachineService.transitionOrderTransactionState(
                    this.transaction.id,
                    this.currentActionName,
                    { documentIds: docIds, sendMail }
                ).then(() => {
                    let transactionId = this.transaction.id;
                    this.PaynlPaymentService.changeTransactionStatus({transactionId, currentActionName})
                        .then(() => {
                            this.$emit('order-state-change');
                            this.loadHistory();
                        })
                        .catch((errorResponse) => {
                            this.createNotificationError({
                                title: this.$tc('sw-plugin-config.titleSaveError'),
                                message: errorResponse,
                            });
                        })
                }).catch((error) => {
                    this.createStateChangeErrorNotification(error);
                });
            } else if (this.currentStateType === 'orderState') {
                this.orderStateMachineService.transitionOrderState(
                    this.order.id,
                    this.currentActionName,
                    { documentIds: docIds, sendMail }
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
                    { documentIds: docIds, sendMail }
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

