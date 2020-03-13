const { Component } = Shopware;

Component.override('sw-order-state-history-card', {
    inject: ['PaynlPaymentService'],

    methods: {
        onLeaveModalConfirm(docIds) {
            this.showModal = false;
            let currentActionName = this.currentActionName;
            if (this.currentStateType === 'orderTransactionState') {
                this.orderStateMachineService.transitionOrderTransactionState(
                    this.transaction.id,
                    this.currentActionName,
                    { documentIds: docIds }
                ).then(() => {
                    this.$emit('order-state-change');
                    this.loadHistory();
                    this.paynlChangeTransactionStatus(this.transaction.id, currentActionName);
                }).catch((error) => {
                    this.createStateChangeErrorNotification(error);
                });
            } else if (this.currentStateType === 'orderState') {
                this.orderStateMachineService.transitionOrderState(
                    this.order.id,
                    this.currentActionName,
                    { documentIds: docIds }
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
                    { documentIds: docIds }
                ).then(() => {
                    this.$emit('order-state-change');
                    this.loadHistory();
                }).catch((error) => {
                    this.createStateChangeErrorNotification(error);
                });
            }
            this.currentActionName = null;
            this.currentStateType = null;
        },

        paynlChangeTransactionStatus(transactionId, currentActionName) {
            this.PaynlPaymentService.changeTransactionStatus({transactionId, currentActionName})
                .then((response) => {})
                .catch((errorResponse) => {
                    this.createNotificationError({
                        title: this.$tc('sw-plugin-config.titleSaveError'),
                        message: errorResponse,
                    });
                })
        }
    }
});

