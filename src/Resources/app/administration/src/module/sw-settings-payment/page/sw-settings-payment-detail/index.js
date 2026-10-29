import template from './sw-settings-payment-detail.html.twig'
import VersionCompare from './../../../../util/version-compare.util'

const { Component, Context } = Shopware
const { Criteria } = Shopware.Data

Component.override('sw-settings-payment-detail', {
    template,

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
        isShopware67() {
            return this.versionCompare.isGreaterOrEqual(Context.app.config.version, '6.7');
        },
    },

    methods: {
        createdComponent() {
            this.$super('createdComponent');

            this.initPaymentSurchargeData();
        },

        initPaymentSurchargeData() {
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

        saveFinish() {
            this.$super('saveFinish')

            if (this.paymentMethodId && this.paymentSurcharge.amount >= 0.0) {
                this.paymentSurcharge.id = this.paymentMethodId
                this.paymentSurcharge.paymentMethodId = this.paymentMethodId

                this.paymentSurchargeRepository.save(this.paymentSurcharge, Shopware.Context.api)
            }
        }
    }
})
