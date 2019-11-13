import template from './transactions-list.html.twig';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('transactions-list-component', {
    template,

    inject: [
        'repositoryFactory',
        'context'
    ],

    data() {
        return {
            repository: null,
            transactions: null,
            test: null
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },

    computed: {
        columns() {
            return [{
                property: 'paynlTransactionId',
                dataIndex: 'paynlTransactionId',
                label: this.$t('transactions-list.paynl_transaction_id'),
                allowResize: true,
                sortIsAllowed: true,
                primary: true
            },
            {
                property: 'paymentId',
                dataIndex: 'paymentId',
                label: this.$t('transactions-list.payment_id'),
                sortIsAllowed: true,
                allowResize: true,
            },
            {
                property: 'amount',
                dataIndex: 'amount',
                label: this.$t('transactions-list.amount'),
                allowResize: true,
                sortIsAllowed: true,
                primary: true
            },
            {
                property: 'currency',
                dataIndex: 'currency',
                label: this.$t('transactions-list.currency'),
                allowResize: true,
                sortIsAllowed: true,
                primary: true
            },
            {
                property: 'createdAt',
                dataIndex: 'createdAt',
                label: this.$t('transactions-list.created_at'),
                sortIsAllowed: true,
                allowResize: true,
            },
            {
                property: 'links',
                dataIndex: 'links',
                label: this.$t('transactions-list.links'),
                allowResize: true,
                sortIsAllowed: false,
                align: 'center'
            }];
        }
    },

    created() {
        this.repository = this.repositoryFactory.create('paynl_transactions');
        this.repository
            .search(new Criteria(), this.context)
            .then((result) => {
                this.transactions = result;
            });
    }
});
