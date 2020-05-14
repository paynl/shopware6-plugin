const { Component, Mixin } = Shopware;

import template from './paynl-plugin-settings.html.twig';

Component.register('paynl-plugin-settings', {
    template,

    mixins: [
        Mixin.getByName('notification')
    ],

    inject: [ 'PaynlPaymentService' ],

    data() {
        return {
            isInstallLoading: false,
            isLoading: false,
            isTesting: false,
            isSaveSuccessful: false,
            isInstallSuccessful: false,
            isTestSuccessful: false,
            config: {},
            tokenCodeFilled: false,
            apiTokenFilled: false,
            serviceIdFilled: false,
            testModeFilled: false,
            allowRefundsFilled: false,
            femaleSalutationsFilled: false,
            showValidationErrors: false
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },

    methods: {
        saveFinish() {
            this.isSaveSuccessful = false;
        },

        installFinish() {
            this.isInstallSuccessful = false;
        },

        onConfigChange(config) {
            this.config = config;
        },

        getConfigValue(field) {
            const defaultConfig = this.$refs.systemConfig.actualConfigData.null;
            const salesChannelId = this.$refs.systemConfig.currentSalesChannelId;

            if (salesChannelId === null) {
                return this.config[`PaynlPaymentShopware6.settings.${field}`];
            }

            return this.config[`PaynlPaymentShopware6.settings.${field}`]
                || defaultConfig[`PaynlPaymentShopware6.settings.${field}`];
        },

        onSave() {
            this.isSaveSuccessful = false;
            this.isLoading = true;
            this.$refs.systemConfig.saveAll().then(() => {
                this.isLoading = false;
                this.isSaveSuccessful = true;
                this.createNotificationSuccess({
                    title: this.$tc('sw-plugin-config.titleSaveSuccess'),
                    message: this.$tc('sw-plugin-config.messageSaveSuccess')
                });
            }).catch(() => {
                this.isLoading = false;
            });
        },

        onInstallPaymentMethods() {
            this.isInstallLoading = true;
            this.$refs.systemConfig.saveAll().then(() => {
                this.createNotificationSuccess({
                    title: this.$tc('sw-plugin-config.titleSaveSuccess'),
                    message: this.$tc('sw-plugin-config.messageSaveSuccess')
                });
                this.isInstallSuccessful = false;

                if (this.isCredentialsEmpty()) {
                    this.createNotificationError({
                        title: this.$tc('paynlValidation.error.paymentMethodsInstallLabel'),
                        message: this.$tc('paynlValidation.error.wrongCredentials')
                    });

                    this.isInstallSuccessful = true;
                    this.isInstallLoading = false;
                } else {
                    this.PaynlPaymentService.installPaymentMethods()
                        .then((response) => {
                            this.createNotificationSuccess({
                                title: this.$tc('sw-plugin-config.titleSaveSuccess'),
                                message: response.message
                            });

                            this.isInstallSuccessful = true;
                            this.isInstallLoading = false;
                        })
                        .catch(() => {
                            this.createNotificationError({
                                title: this.$tc('paynlValidation.error.paymentMethodsInstallLabel'),
                                message: this.$tc('paynlValidation.error.paymentMethodsInstallMessage')
                            });

                            this.isInstallSuccessful = true;
                            this.isInstallLoading = false;
                        });
                }
            }).catch((error) => {
                this.createNotificationError({
                    title: this.$tc('sw-plugin-config.titleSaveError'),
                    message: error
                });

                this.isInstallSuccessful = true;
                this.isInstallLoading = false;
            });
        },

        isCredentialsEmpty() {
            return !(this.tokenCodeFilled && this.apiTokenFilled && this.serviceIdFilled);
        },

        setCredentialsFilled() {
            this.tokenCodeFilled = !!this.getConfigValue('tokenCode');
            this.apiTokenFilled = !!this.getConfigValue('apiToken');
            this.serviceIdFilled = !!this.getConfigValue('serviceId');
        },

        getBind(element, config) {
            this.setCredentialsFilled();

            if (config !== this.config) {
                this.onConfigChange(config);
            }

            return element;
        }
    }
});
