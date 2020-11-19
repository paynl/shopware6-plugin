const { Component } = Shopware;
import template from './sw-order-user-card.html.twig';

Component.override('sw-order-user-card', {
    template,

    data() {
        return {
            payIssuer: null
        };
    },

    created() {
        this.getPayIssuer();
    },

    methods: {
        getPayIssuer() {
            if (this.currentOrder.customFields !== null) {
                let issuer = this.currentOrder.customFields.paynlIssuer;
                if (issuer) {
                    this.currentOrder.transactions.last().paymentMethod.customFields.banks.forEach((bank) => {
                        if (bank.id === issuer) {
                            this.payIssuer = bank.visibleName;
                        }
                    });
                }
            }
        }
    }
});
