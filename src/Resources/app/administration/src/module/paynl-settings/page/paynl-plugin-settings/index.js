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
            isLoading: false,
            isTesting: false,
            isSaveSuccessful: false,
            config: {},
            currentSalesChannelId: null,
            settingsData: {
                tokenCode: null,
                allowRefunds: null,
                surchargePaymentMethods: null,
                apiToken: null,
                serviceId: null,
                failOverGateway: null,
                useSinglePaymentMethod: null,
                testMode: null,
                cocNumberRequired: null,
                usePAYStyles: null,
                showDescription: null,
                additionalAddressFields: null,
                femaleSalutations: null,
                paymentScreenLanguage: null,
                transferGoogleAnalytics: null,
                ipSettings: null,
                automaticShipping: null,
                automaticCancellation: null,
                orderStateWithPaidTransaction: null,
                orderStateWithFailedTransaction: null,
                orderStateWithCancelledTransaction: null,
                orderStateWithAuthorizedTransaction: null,
                paymentPinTerminal: null
            },
            collapsibleState: {},
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },

    computed: {
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
            this.currentSalesChannelId = this.$refs.systemConfig.currentSalesChannelId ? this.$refs.systemConfig.currentSalesChannelId : '';

            this.config = config;

            this.settingsData = {
                tokenCode: this.config['PaynlPaymentShopware6.config.tokenCode'],
                allowRefunds: this.config['PaynlPaymentShopware6.config.allowRefunds'],
                surchargePaymentMethods: this.config['PaynlPaymentShopware6.config.surchargePaymentMethods'],
                apiToken: this.config['PaynlPaymentShopware6.config.apiToken'],
                serviceId: this.config['PaynlPaymentShopware6.config.serviceId'],
                failOverGateway: this.config['PaynlPaymentShopware6.config.failOverGateway'],
                useSinglePaymentMethod: this.config['PaynlPaymentShopware6.config.useSinglePaymentMethod'],
                testMode: this.config['PaynlPaymentShopware6.config.testMode'],
                cocNumberRequired: this.config['PaynlPaymentShopware6.config.cocNumberRequired'],
                showDescription: this.config['PaynlPaymentShopware6.config.showDescription'],
                additionalAddressFields: this.config['PaynlPaymentShopware6.config.additionalAddressFields'],
                femaleSalutations: this.config['PaynlPaymentShopware6.config.femaleSalutations'],
                usePAYStyles: this.config['PaynlPaymentShopware6.config.usePAYStyles'],
                paymentScreenLanguage: this.config['PaynlPaymentShopware6.config.paymentScreenLanguage'],
                transferGoogleAnalytics: this.config['PaynlPaymentShopware6.config.transferGoogleAnalytics'],
                ipSettings: this.config['PaynlPaymentShopware6.config.ipSettings'],
                automaticShipping: this.config['PaynlPaymentShopware6.config.automaticShipping'],
                automaticCancellation: this.config['PaynlPaymentShopware6.config.automaticCancellation'],
                orderStateWithPaidTransaction: this.config['PaynlPaymentShopware6.config.orderStateWithPaidTransaction'],
                orderStateWithFailedTransaction: this.config['PaynlPaymentShopware6.config.orderStateWithFailedTransaction'],
                orderStateWithCancelledTransaction: this.config['PaynlPaymentShopware6.config.orderStateWithCancelledTransaction'],
                orderStateWithAuthorizedTransaction: this.config['PaynlPaymentShopware6.config.orderStateWithAuthorizedTransaction'],
                paymentPinTerminal: this.config['PaynlPaymentShopware6.config.paymentPinTerminal'],
            };
        },

        getConfigValue(field) {
            const defaultConfig = this.$refs.systemConfig.actualConfigData.null;
            const salesChannelId = this.$refs.systemConfig.currentSalesChannelId;

            if (salesChannelId === null) {
                return this.config[`PaynlPaymentShopware6.config.${field}`];
            }

            return this.config[`PaynlPaymentShopware6.config.${field}`]
                || defaultConfig[`PaynlPaymentShopware6.config.${field}`];
        },

        onSave() {
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

        bindField(element, config) {
            if (config !== this.config) {
                this.onConfigChange(config);
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
