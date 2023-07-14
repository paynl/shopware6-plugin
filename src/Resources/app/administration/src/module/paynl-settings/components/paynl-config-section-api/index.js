import template from './paynl-config-section-api.html.twig';
import './paynl-config-section-api.scss';

const {Mixin} = Shopware;

export default {
    template,

    inject: [
        'PaynlPaymentService',
    ],

    mixins: [
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            testCredentialsIsLoading: false,
        };
    },

    methods: {
        onInstallPaymentMethods() {
            // Shopware > 6.4.7.0
            const configRootNew = this.$parent.$parent.$parent.$parent.$parent;
            // Shopware <= 6.4.7.0
            const configRootOld = this.$parent.$parent.$parent.$parent;
            const configRoot = configRootNew ? configRootNew : configRootOld;
            let salesChannelId = '';
            if (configRoot) {
                salesChannelId = configRoot.currentSalesChannelId ? configRoot.currentSalesChannelId : '';
            }

            configRoot.saveAll().then(() => {
                this.createNotificationSuccess({
                    title: this.$tc('paynlDefault.success'),
                    message: this.$tc('paynlValidation.messages.paymentMethodsSuccessfullyInstalled')
                });
            }).catch((error) => {
                this.createNotificationError({
                    title: this.$tc('paynlValidation.error.paymentMethodsInstallLabel'),
                    message: error.message
                });
            });
        },

        onTestCredentials() {
            const tokenCodeInput = document.querySelector('input[name="PaynlPaymentShopware6.config.tokenCode"]');
            const apiTokenInput = document.querySelector('input[name="PaynlPaymentShopware6.config.apiToken"]');
            const serviceIdInput = document.querySelector('input[name="PaynlPaymentShopware6.config.serviceId"]');


            const tokenCode = tokenCodeInput ? tokenCodeInput.value : null;
            const apiToken = apiTokenInput ? apiTokenInput.value : null;
            const serviceId = serviceIdInput ? serviceIdInput.value : null;

            this.startTestCredentials();
            this.PaynlPaymentService.testApiKeys({tokenCode, apiToken, serviceId})
                .then((response) => {
                    this.testCredentialsIsDone();
                    this.createNotificationSuccess({
                        title: this.$tc('paynlDefault.success'),
                        message: this.$tc(response.message)
                    });
                })
                .catch((error) => {
                    this.testCredentialsIsDone();
                    this.createNotificationError({
                        title: this.$tc('paynlDefault.error'),
                        message: this.$tc(error.response.data.message)
                    });
                });
        },

        startTestCredentials() {
            this.testCredentialsIsLoading = true;
        },

        testCredentialsIsDone() {
            this.testCredentialsIsLoading = false;
        },
    },
};
