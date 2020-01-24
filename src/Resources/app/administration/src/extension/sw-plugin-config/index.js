import {Component} from 'src/core/shopware';
import template from './sw-plugin-config.html.twig';

Component.override('sw-plugin-config', {
    template,

    inject: ['PaynlPaymentService'],

    data() {
        const domain = `${this.$route.params.namespace}.config`;
        return {
            isLoading: false,
            namespace: this.$route.params.namespace,
            domain: domain,
            salesChannelId: null,
            config: {},
            actualConfigData: {}
        };
    },

    computed: {
        isPaynlPayment()
        {
            return 'PaynlPayment' === this.$route.params.namespace;
        }
    },

    methods: {
        installPaymentMethod() {
            this.isLoading = true;
            this.onSavePaynl(() => {
                this.PaynlPaymentService.installPaymentMethods()
                    .then((response) => {
                        this.createNotificationSuccess({
                            title: this.$tc('sw-plugin-config.titleSaveSuccess'),
                            message: response.message
                        });
                    })
                    .catch((errorResponse) => {
                        this.createNotificationError({
                            title: this.$tc('sw-plugin-config.titleSaveError'),
                            message: 'Wrong credentials.',
                        });
                    });
            });
        },

        onSavePaynl(callback = null) {
            this.$refs.systemConfig.saveAll().then(() => {
                this.createNotificationSuccess({
                    title: this.$tc('sw-plugin-config.titleSaveSuccess'),
                    message: this.$tc('sw-plugin-config.messageSaveSuccess')
                });
                if (callback !== null) {
                    callback();
                }
            }).catch((error) => {
                this.createNotificationError({
                    title: this.$tc('sw-plugin-config.titleSaveError'),
                    message: error
                });
            });
        }
    }
});

