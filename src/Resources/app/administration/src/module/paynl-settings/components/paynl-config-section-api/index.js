import template from './paynl-config-section-api.html.twig';


// eslint-disable-next-line no-undef
const {Component, Mixin} = Shopware;

Component.register('paynl-config-section-api', {
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
            installPaymentMethodsIsLoading: false,
        };
    },

    methods: {
        onInstallPaymentMethods() {
            let configRoot = this.$parent;
            while (configRoot.saveAll === undefined) {
                configRoot = configRoot.$parent;
            }

            let salesChannelId = '';
            if (configRoot) {
                salesChannelId = configRoot.currentSalesChannelId ? configRoot.currentSalesChannelId : '';
            }

            const tokenCodeInput = document.querySelector('input[name="PaynlPaymentShopware6.config.tokenCode"]');
            const apiTokenInput = document.querySelector('input[name="PaynlPaymentShopware6.config.apiToken"]');
            const serviceIdInput = document.querySelector('input[name="PaynlPaymentShopware6.config.serviceId"]');

            const tokenCode = tokenCodeInput ? tokenCodeInput.value : null;
            const apiToken = apiTokenInput ? apiTokenInput.value : null;
            const serviceId = serviceIdInput ? serviceIdInput.value : null;

            if (!tokenCode || !apiToken || !serviceId) {
                if (!tokenCode) {
                    this.createNotificationError({
                        title: this.$tc('paynlValidation.error.wrongCredentials'),
                        message: tokenCodeInput.getAttribute('label'),
                    });
                }

                if (!apiToken) {
                    this.createNotificationError({
                        title: this.$tc('paynlValidation.error.wrongCredentials'),
                        message: apiTokenInput.getAttribute('label'),
                    });
                }

                if (!serviceId) {
                    this.createNotificationError({
                        title: this.$tc('paynlValidation.error.wrongCredentials'),
                        message: serviceIdInput.getAttribute('label'),
                    });
                }

                return;
            }

            this.startInstallPaymentMethods();
            configRoot.saveAll().then(() => {
                this.PaynlPaymentService.installPaymentMethods(salesChannelId)
                    .then((response) => {
                        this.installPaymentMethodsIsDone();
                        if (response.success) {
                            this.createNotificationSuccess({
                                title: this.$tc('paynlDefault.success'),
                                message: this.$tc(response.message)
                            });
                        } else {
                            this.createNotificationError({
                                title: this.$tc('paynlDefault.error'),
                                message: this.$tc(response.message)
                            });
                        }
                    })
                    .catch((error) => {
                        this.createNotificationError({
                            title: this.$tc('paynlDefault.error'),
                            message: error
                        });
                    });
            }).catch((error) => {
                this.installPaymentMethodsIsDone();
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
                    if (response.success) {
                        this.createNotificationSuccess({
                            title: this.$tc('paynlDefault.success'),
                            message: this.$tc(response.message)
                        });
                    } else {
                        this.createNotificationError({
                            title: this.$tc('paynlDefault.error'),
                            message: this.$tc(response.message)
                        });
                    }
                })
                .catch((error) => {
                    this.testCredentialsIsDone();
                    this.createNotificationError({
                        title: this.$tc('paynlDefault.error'),
                        message: error
                    });
                });
        },

        startTestCredentials() {
            this.testCredentialsIsLoading = true;
        },

        testCredentialsIsDone() {
            this.testCredentialsIsLoading = false;
        },

        startInstallPaymentMethods() {
            this.installPaymentMethodsIsLoading = true;
        },

        installPaymentMethodsIsDone() {
            this.installPaymentMethodsIsLoading = false;
        },
    },
});
