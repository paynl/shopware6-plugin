const { Component } = Shopware;
import template from './sw-order-user-card.html.twig';
import formatPaymentMethodLabel from '../../util/payment-method-label.util';

Component.override('sw-order-user-card', {
    template,

    computed: {
        paymentMethodLabel() {
            const customFields = this.currentOrder.customFields || {};

            return formatPaymentMethodLabel(
                this.currentOrder.transactions.last().paymentMethod.translated.distinguishableName,
                customFields.paynlIssuerName
            );
        }
    }
});
