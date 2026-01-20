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
        getConfigInputs() {
            const fields = {
                tokenCode: {
                    name: 'PaynlPaymentShopware6.config.tokenCode',
                    aria: 'Token-Code',
                },
                apiToken: {
                    name: 'PaynlPaymentShopware6.config.apiToken',
                    aria: 'API-token',
                },
                serviceId: {
                    name: 'PaynlPaymentShopware6.config.serviceId',
                    aria: 'Service-ID',
                },
            };

            const result = {};

            Object.keys(fields).forEach((key) => {
                const { name, aria } = fields[key];

                // Old Shopware (<= 6.6)
                let input = document.querySelector(`input[name="${name}"]`);

                // New Shopware (6.7+)
                if (!input) {
                    input = document.querySelector(`input[aria-label*="${aria}"]`);
                }

                result[key] = {
                    input,
                    value: input ? input.value : null,
                    label: input ? (input.getAttribute('aria-label') || aria) : aria,
                };
            });

            return result;
        },

        validateConfigInputs(configInputs) {
            let valid = true;

            Object.values(configInputs).forEach((field) => {
                if (!field.value) {
                    valid = false;

                    this.createNotificationError({
                        title: this.$tc('paynlValidation.error.wrongCredentials'),
                        message: field.label,
                    });
                }
            });

            return valid;
        },

        /**
         * INSTALL PAYMENT METHODS
         */
        onInstallPaymentMethods() {
            if (this.installPaymentMethodsIsLoading) {
                return;
            }

            // Find config root
            let configRoot = this.$parent;
            while (configRoot && configRoot.saveAll === undefined) {
                configRoot = configRoot.$parent;
            }

            const salesChannelId = configRoot && configRoot.currentSalesChannelId
                ? configRoot.currentSalesChannelId
                : '';

            const configInputs = this.getConfigInputs();

            if (!this.validateConfigInputs(configInputs)) {
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
                                message: this.$tc(response.message),
                            });
                        } else {
                            this.createNotificationError({
                                title: this.$tc('paynlDefault.error'),
                                message: this.$tc(response.message),
                            });
                        }
                    })
                    .catch((error) => {
                        this.installPaymentMethodsIsDone();
                        this.createNotificationError({
                            title: this.$tc('paynlDefault.error'),
                            message: error,
                        });
                    });
            }).catch((error) => {
                this.installPaymentMethodsIsDone();
                this.createNotificationError({
                    title: this.$tc('paynlValidation.error.paymentMethodsInstallLabel'),
                    message: error.message,
                });
            });
        },

        /**
         * TEST CREDENTIALS
         */
        onTestCredentials() {
            if (this.testCredentialsIsLoading) {
                return;
            }

            const configInputs = this.getConfigInputs();

            if (!this.validateConfigInputs(configInputs)) {
                return;
            }

            const payload = {
                tokenCode: configInputs.tokenCode.value,
                apiToken: configInputs.apiToken.value,
                serviceId: configInputs.serviceId.value,
            };

            this.startTestCredentials();

            this.PaynlPaymentService.testApiKeys(payload)
                .then((response) => {
                    this.testCredentialsIsDone();

                    if (response.success) {
                        this.createNotificationSuccess({
                            title: this.$tc('paynlDefault.success'),
                            message: this.$tc(response.message),
                        });
                    } else {
                        this.createNotificationError({
                            title: this.$tc('paynlDefault.error'),
                            message: this.$tc(response.message),
                        });
                    }
                })
                .catch((error) => {
                    this.testCredentialsIsDone();
                    this.createNotificationError({
                        title: this.$tc('paynlDefault.error'),
                        message: error,
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
