import template from './sw-settings-payment-detail.html.twig'
import VersionCompare from './../../../../util/version-compare.util'

const IDEAL_PAYMENT_ID = 10;
const PAYPAL_PAYMENT_ID = 138;
const PAYMENT_IDEAL_EXPRESS_MODAL_ENABLED = 'PaynlPaymentShopware6.config.paymentIdealExpressModalEnabled';

const { Component, Context } = Shopware
const { Criteria } = Shopware.Data

Component.override('sw-settings-payment-detail', {
    template,

    inject: ['systemConfigApiService'],

    watch: {
        paymentMethod(){
            if (!this.paymentMethod) {
                this.paymentMethod = {};
            }

            if (!this.paymentMethod.id) {
                this.paymentMethod.id = null;
            }

            if (!this.paymentMethod.customFields) {
                this.paymentMethod.customFields = {};
            }
        }
    },

    created() {
        this.versionCompare = new VersionCompare();
    },

    data() {
        return {
            config: {},
            isExpressCheckoutPaymentMethod: false,
            isIDEALPaymentMethod: false,
            isPayPalPaymentMethod: false,
            paymentSurcharge: {},
            surchargeTypes: [
                {
                    value: 'absolute',
                    label: this.$tc(
                        'paymentSurchargeSettings.type.absoluteOptionLabel'
                    )
                },
                {
                    value: 'percentage',
                    label: this.$tc(
                        'paymentSurchargeSettings.type.percentageOptionLabel'
                    )
                }
            ],
            versionCompare: null,
        }
    },

    computed: {
        paymentSurchargeRepository() {
            return this.repositoryFactory.create('paynl_payment_surcharge')
        },
        paymentMethodRepository() {
            return this.repositoryFactory.create('payment_method');
        },
        isShopware67() {
            return this.versionCompare.isGreaterOrEqual(Context.app.config.version, '6.7');
        },
    },

    methods: {
        createdComponent() {
            this.$super('createdComponent');

            this.initPaymentSurchargeData();

            this.fetchPayConfig();
        },

        initPaymentSurchargeData() {
            this.paymentMethodRepository.get(this.paymentMethodId)
                .then((paymentMethod) => {
                    this.paymentMethod = paymentMethod;
                    this.isIDEALPaymentMethod = paymentMethod.customFields.paynlId === IDEAL_PAYMENT_ID;
                    this.isPayPalPaymentMethod = paymentMethod.customFields.paynlId === PAYPAL_PAYMENT_ID;
                    this.isExpressCheckoutPaymentMethod = this.isIDEALPaymentMethod || this.isPayPalPaymentMethod;
                });

            const criteria = new Criteria()
            criteria.addFilter(
                Criteria.equals('paymentMethodId', this.paymentMethodId)
            )

            this.paymentSurchargeRepository
                .search(criteria, Shopware.Context.api)
                .then((paymentSurcharges) => {
                    const paymentSurcharge = paymentSurcharges.first()
                    if (paymentSurcharge) {
                        this.paymentSurcharge = paymentSurcharge
                    } else {
                        this.paymentSurcharge =
                            this.paymentSurchargeRepository.create(
                                Shopware.Context.api,
                                [
                                    {
                                        amount: 0.0,
                                        orderValueLimit: 0.0,
                                        type: 'absolute',
                                        paymentMethodId: this.paymentMethodId
                                    }
                                ]
                            )
                    }
                })
                .catch((e) => {
                    this.paymentSurcharge =
                        this.paymentSurchargeRepository.create(
                            Shopware.Context.api,
                            [
                                {
                                    amount: 0.0,
                                    orderValueLimit: 0.0,
                                    type: 'absolute',
                                    paymentMethodId: this.paymentMethodId
                                }
                            ]
                        )
                })
        },

        fetchPayConfig() {
            this.isLoading = true;
            return this.systemConfigApiService.getValues('PaynlPaymentShopware6.config', null)
                .then(values => {
                    // make IDEAL modal view enabled by default
                    if (!values.hasOwnProperty(PAYMENT_IDEAL_EXPRESS_MODAL_ENABLED)) {
                        values[PAYMENT_IDEAL_EXPRESS_MODAL_ENABLED] = true;
                    }

                    this.config = values;
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        saveConfig() {
            this.isLoading = true;
            return this.systemConfigApiService.saveValues(this.config, null)
                .then(() => {
                    this.isLoading = false;
                });
        },

        saveFinish() {
            this.$super('saveFinish')
            if (this.isExpressCheckoutPaymentMethod) {
                this.saveConfig();
            }

            if (this.paymentMethodId && this.paymentSurcharge.amount >= 0.0) {
                this.paymentSurcharge.id = this.paymentMethodId
                this.paymentSurcharge.paymentMethodId = this.paymentMethodId

                this.paymentSurchargeRepository.save(this.paymentSurcharge, Shopware.Context.api)
            }
        }
    }
})
