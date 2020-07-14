const { Component } = Shopware;

Component.override('sw-order-detail-base', {
    data: {
        authorizeStateMachineState: 'authorize',
        partlyCapturedStateMachineState: 'partly_captured',
        verifyStateMachineState: 'verify',
    },
    methods: {
        onStateTransitionOptionsChanged(stateMachineName, options) {
            if (stateMachineName === 'order.states') {
                this.orderOptions = options;
            } else if (stateMachineName === 'order_transaction.states') {
                this.transactionOptions = this.modifyOptions(options)
            } else if (stateMachineName === 'order_delivery.states') {
                this.deliveryOptions = options;
            }
        },

        modifyOptions(originalOptions) {
            const authorizeStateMachineState = 'authorize';
            const partlyCapturedStateMachineState = 'partly_captured';

            let currentTechnicalName = this.transaction.stateMachineState.technicalName;
            if (currentTechnicalName === authorizeStateMachineState || partlyCapturedStateMachineState === currentTechnicalName) {
                for (let index = 0; index < originalOptions.length; index++) {
                    if (originalOptions[index].stateName === 'paid') {
                        originalOptions[index].name = this.$tc('order.transaction.paid');
                    }
                    if (originalOptions[index].stateName === 'cancelled') {
                        originalOptions[index].name = this.$tc('order.transaction.cancelled');
                    }
                }
            }

            return originalOptions;
        }
    }
});
