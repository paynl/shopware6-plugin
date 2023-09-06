import template from './sw-settings-payment-detail.html.twig'

const { Component } = Shopware
const { Criteria } = Shopware.Data

Component.override('sw-settings-payment-detail', {
    template,

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
            ]
        }
    },

    computed: {
        paymentSurchargeRepository() {
            return this.repositoryFactory.create('paynl_payment_surcharge')
        }
    },

    methods: {
        createdComponent() {
            this.$super('createdComponent')

            this.initPaymentSurchargeData()
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
