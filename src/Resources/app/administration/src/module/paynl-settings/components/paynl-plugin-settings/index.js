const { Component, Mixin } = Shopware;

import template from './paynl-plugin-settings.html.twig';

Component.register('paynl-plugin-settings', {
    template,

    mixins: [
        Mixin.getByName('notification')
    ],

    inject: [
        'PaynlPaymentService',
        'acl'
    ],

    props: ['disabled'],

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
            useSinglePaymentMethodFilled: false,
            testModeFilled: false,
            usePAYStylesFilled: false,
            cocNumberRequiredFilled: false,
            allowRefundsFilled: false,
            femaleSalutationsFilled: false,
            showCredentilasErrors: false,
            settingsData: {
                tokenCode: null,
                allowRefunds: null,
                apiToken: null,
                serviceId: null,
                useSinglePaymentMethod: null,
                testMode: null,
                cocNumberRequired: null,
                usePAYStyles: null,
                showDescription: null,
                additionalAddressFields: null,
                femaleSalutations: null
            }
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },

    computed: {
        credentialsEmpty: function() {
            return !this.tokenCodeFilled || !this.apiTokenFilled || !this.serviceIdFilled;
        },

        isDisabled: function () {
            return this.isLoading || !this.acl.can('paynl.editor');
        }
    },

    mounted() {
        this.$nextTick(function () {
            if (!this.acl.can('paynl.editor')) {
                this.disableSalesChannel();
            }
        })
    },

    methods: {
        saveFinish() {
            this.isSaveSuccessful = false;
        },

        installFinish() {
            this.isInstallSuccessful = false;
        },

        disableSalesChannel() {
            var $this = this;
            var waitSalesChannelInit = setInterval(function() {
                let systemConfig = $this.$refs.systemConfig;
                if (systemConfig === undefined
                    || systemConfig.$children === undefined
                    || systemConfig.$children[0] === undefined
                ) {
                    return;
                }

                let systemConfigSettings = systemConfig.$children[0];
                if (systemConfigSettings.$children === undefined || systemConfigSettings.$children[0] === undefined) {
                    return;
                }

                let salesChannelSwitch = systemConfigSettings.$children[0];
                salesChannelSwitch.disabled = true;

                clearInterval(waitSalesChannelInit);
            }, 100); // check every 100ms
        },

        onConfigChange(config) {
            this.config = config;

            this.setCredentialsFilled();

            this.settingsData = {
                tokenCode: this.config['PaynlPaymentShopware6.settings.tokenCode'],
                allowRefunds: this.config['PaynlPaymentShopware6.settings.allowRefunds'],
                apiToken: this.config['PaynlPaymentShopware6.settings.apiToken'],
                serviceId: this.config['PaynlPaymentShopware6.settings.serviceId'],
                useSinglePaymentMethod: this.config['PaynlPaymentShopware6.settings.useSinglePaymentMethod'],
                testMode: this.config['PaynlPaymentShopware6.settings.testMode'],
                cocNumberRequired: this.config['PaynlPaymentShopware6.settings.cocNumberRequired'],
                showDescription: this.config['PaynlPaymentShopware6.settings.showDescription'],
                additionalAddressFields: this.config['PaynlPaymentShopware6.settings.additionalAddressFields'],
                femaleSalutations: this.config['PaynlPaymentShopware6.settings.femaleSalutations'],
                usePAYStyles: this.config['PaynlPaymentShopware6.settings.usePAYStyles'],
            };

            this.showCredentilasErrors = false;
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
            if (this.credentialsEmpty) {
                this.showCredentilasErrors = true;
                return;
            }

            this.isSaveSuccessful = false;
            this.isLoading = true;

            this.PaynlPaymentService.storeSettings(this.settingsData)
                .then((response) => {
                    if (response.success === true) {
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

                    this.isLoading = false;
                    this.isSaveSuccessful = true;
                })
                .catch((error) => {
                    this.createNotificationError({
                        title: this.$tc('paynlDefault.error'),
                        message: error
                    });

                    this.isLoading = false;
                });
        },

        onInstallPaymentMethods() {
            if (this.settingsData.useSinglePaymentMethod) {
                return;
            }

            if (this.credentialsEmpty) {
                this.showCredentilasErrors = true;
                return;
            }

            this.isInstallLoading = true;

            this.PaynlPaymentService.storeSettings(this.settingsData)
                .then((response) => {
                    if (response.success === true) {
                        this.createNotificationSuccess({
                            title: this.$tc('paynlDefault.success'),
                            message: this.$tc(response.message)
                        });

                        this.isInstallSuccessful = false;

                        this.PaynlPaymentService.installPaymentMethods()
                            .then((response) => {
                                this.createNotificationSuccess({
                                    title: this.$tc('paynlDefault.success'),
                                    message: this.$tc(response.message)
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
                    } else {
                        this.createNotificationError({
                            title: this.$tc('paynlDefault.error'),
                            message: this.$tc(response.message)
                        });

                        this.isInstallSuccessful = true;
                        this.isInstallLoading = false;
                    }

                    this.isLoading = false;
                    this.isSaveSuccessful = true;
                })
                .catch((error) => {
                    this.createNotificationError({
                        title: this.$tc('paynlDefault.error'),
                        message: error
                    });

                    this.isLoading = false;
                });
        },

        setCredentialsFilled() {
            this.tokenCodeFilled = !!this.getConfigValue('tokenCode');
            this.apiTokenFilled = !!this.getConfigValue('apiToken');
            this.serviceIdFilled = !!this.getConfigValue('serviceId');
        },

        bindField(element, config) {
            if (config !== this.config) {
                this.onConfigChange(config);
            }

            if (this.showCredentilasErrors) {
                if (element.name === 'PaynlPaymentShopware6.settings.tokenCode' && !this.tokenCodeFilled) {
                    element.config.error = {
                        code: 1,
                        detail: this.$tc('paynlValidation.error.shouldNotBeBlank')
                    };
                }
                if (element.name === 'PaynlPaymentShopware6.settings.apiToken' && !this.apiTokenFilled) {
                    element.config.error = {
                        code: 1,
                        detail: this.$tc('paynlValidation.error.shouldNotBeBlank')
                    };
                }
                if (element.name === 'PaynlPaymentShopware6.settings.serviceId' && !this.serviceIdFilled) {
                    element.config.error = {
                        code: 1,
                        detail: this.$tc('paynlValidation.error.shouldNotBeBlank')
                    };
                }
            }

            return element;
        }
    }
});
