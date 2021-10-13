const { Component, Mixin } = Shopware;
const { object, types } = Shopware.Utils;

import template from './paynl-plugin-settings.html.twig';
import './style.scss';

Component.register('paynl-plugin-settings', {
    template,

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('sw-inline-snippet')
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
            paymentInstoreTerminals: [],
            currentSalesChannelId: null,
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
                femaleSalutations: null,
                paymentScreenLanguage: null,
                paymentInstoreTerminal: null
            },
            collapsibleState: {
                'payment_instore': true,
            },
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
        initInstorePaymentTerminals(salesChannelId = '') {
            let me = this;

            this.PaynlPaymentService.getInstorePaymentTerminals(salesChannelId)
                .then((result) => {
                    me.paymentInstoreTerminals = [];
                    result.data.forEach((element) => {
                        me.paymentInstoreTerminals.push({
                            "label": element.label,
                            "value": element.id,
                        })
                    });
                });
        },

        isCollapsible(card) {
            return card.name in this.collapsibleState;
        },

        displayField(element, config, card) {
            if (!(card.name in this.collapsibleState)) {
                return true;
            }

            return !this.collapsibleState[card.name];
        },

        isCollapsed(card) {
            return this.collapsibleState[card.name];
        },

        toggleCollapsible(card) {
            if (!(card.name in this.collapsibleState)) {
                return;
            }

            this.collapsibleState[card.name] = !this.collapsibleState[card.name];
        },

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
            const salesChannelId = this.$refs.systemConfig.currentSalesChannelId ?? '';

            if (salesChannelId !== this.currentSalesChannelId) {
                this.initInstorePaymentTerminals(salesChannelId);
            }
            this.currentSalesChannelId = salesChannelId;

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
                paymentScreenLanguage: this.config['PaynlPaymentShopware6.settings.paymentScreenLanguage'],
                paymentInstoreTerminal: this.config['PaynlPaymentShopware6.settings.paymentInstoreTerminal'],
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

            this.$refs.systemConfig.saveAll().then(() => {
                const salesChannelId = this.$refs.systemConfig.currentSalesChannelId ?
                    this.$refs.systemConfig.currentSalesChannelId : '';

                this.PaynlPaymentService.storeSettings({salesChannelId: salesChannelId})
                    .then(() => {
                        this.isLoading = false;
                        this.isSaveSuccessful = true;

                        this.createNotificationSuccess({
                            title: this.$tc('paynlDefault.success'),
                            message: this.$tc('paynlValidation.messages.settingsSavedSuccessfully')
                        });
                    })
                    .catch((error) => {
                        this.createNotificationError({
                            title: this.$tc('paynlDefault.error'),
                            message: error
                        });

                        this.isLoading = false;
                    });
            }).catch((error) => {
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
            this.isSaveSuccessful = false;
            this.isLoading = true;

            this.$refs.systemConfig.saveAll().then(() => {
                this.isLoading = false;
                this.isSaveSuccessful = true;

                this.createNotificationSuccess({
                    title: this.$tc('paynlDefault.success'),
                    message: this.$tc('paynlValidation.messages.settingsSavedSuccessfully')
                });

                this.isInstallSuccessful = false;

                const salesChannelId = this.$refs.systemConfig.currentSalesChannelId ?
                    this.$refs.systemConfig.currentSalesChannelId : '';

                this.PaynlPaymentService.installPaymentMethods(salesChannelId)
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
            }).catch(() => {
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
        },

        bindOriginalField(element, config) {
            let originalElement;

            element = this.bindField(element, config);

            this.$refs.systemConfig.config.forEach((configElement) => {
                configElement.elements.forEach((child) => {
                    if (child.name === element.name) {
                        originalElement = child;
                        return;
                    }
                });
            });

            return originalElement || element;
        },

        getElementBind(element) {
            const bind = object.deepCopyObject(element);

            // Add inherited values
            if (this.currentSalesChannelId !== null
                && this.inherit
                && this.actualConfigData.hasOwnProperty('null')
                && this.actualConfigData.null[bind.name] !== null) {
                if (bind.type === 'single-select' || bind.config.componentName === 'sw-entity-single-select') {
                    // Add inherited placeholder option
                    bind.placeholder = this.$tc('sw-settings.system-config.inherited');
                } else if (bind.type === 'bool') {
                    // Add inheritedValue for checkbox fields to restore the inherited state
                    bind.config.inheritedValue = this.actualConfigData.null[bind.name] || false;
                } else if (bind.type === 'password') {
                    // Add inherited placeholder and mark placeholder as password so the rendering element
                    // can choose to hide it
                    bind.placeholderIsPassword = true;
                    bind.placeholder = `${this.actualConfigData.null[bind.name]}`;
                } else if (bind.type !== 'multi-select' && !types.isUndefined(this.actualConfigData.null[bind.name])) {
                    // Add inherited placeholder
                    bind.placeholder = `${this.actualConfigData.null[bind.name]}`;
                }
            }

            // Add select properties
            if (['single-select', 'multi-select'].includes(bind.type)) {
                bind.config.labelProperty = 'name';
                bind.config.valueProperty = 'id';
            }

            return bind;
        },
    }
});
