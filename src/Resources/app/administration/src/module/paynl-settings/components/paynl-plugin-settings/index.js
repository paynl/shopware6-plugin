const { Component, Mixin } = Shopware;

import template from './paynl-plugin-settings.html.twig';

Component.register('paynl-plugin-settings', {
    template,

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('sw-inline-snippet')
    ],

    inject: [ 'PaynlPaymentService' ],

    data() {
        return {
            isLoading: false,
            isTesting: false,
            isSaveSuccessful: false,
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

    created() {
        this.createdComponent();
    },

    computed: {
        credentialsMissing: function() {
            return !this.tokenCodeFilled || !this.apiTokenFilled || !this.serviceIdFilled;
        }
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

        onConfigChange(config) {
            this.config = config;

            this.checkCredentialsFilled();

            this.showValidationErrors = false;
        },

        checkCredentialsFilled() {
            this.tokenCodeFilled = !!this.getConfigValue('tokenCode');
            this.apiTokenFilled = !!this.getConfigValue('apiToken');
            this.serviceIdFilled = !!this.getConfigValue('serviceId');
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
            if (this.credentialsMissing) {
                this.showValidationErrors = true;
                return;
            }

            this.isSaveSuccessful = false;
            this.isLoading = true;
            this.$refs.systemConfig.saveAll().then(() => {
                this.isLoading = false;
                this.isSaveSuccessful = true;
            }).catch(() => {
                this.isLoading = false;
            });
        },

        onInstallPaymentMethods() {
            this.onSave();
        },

        getBind(element, config) {
            if (config !== this.config) {
                this.onConfigChange(config);
            }
            if (this.showValidationErrors) {
                if (element.name === 'PaynlPaymentShopware6.settings.merchantId' && !this.merchantIdFilled) {
                    element.config.error = {
                        code: 1,
                        detail: this.$tc('paynlSettings.messageNotBlank')
                    };
                }
            }

            return element;
        }
    }
});
