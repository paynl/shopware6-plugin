import template from './transactions-list.html.twig';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

const STATUS_PENDING = 17;
const STATUS_CANCEL = 35;
const STATUS_PAID = 12;
const STATUS_NEEDS_REVIEW = 21;
const STATUS_REFUND = 20;
const STATUS_AUTHORIZED = 18;

Component.register('transactions-list-component', {
    template,

    inject: [
        'repositoryFactory',
        'context',
        'stateStyleDataProviderService'
    ],

    data() {
        return {
            repository: null,
            transactions: null,
            statuses: {
                17: 'pending',
                35: 'cancel',
                12: 'paid',
                21: 'needs_review',
                20: 'refund',
                18: 'authorized'
            }
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },

    computed: {
        columns() {
            return [
            {
                property: 'createdAt',
                dataIndex: 'createdAt',
                label: this.$t('transactions-list.created_at'),
                sortIsAllowed: true,
                allowResize: true,
            },
            {
                property: 'updatedAt',
                dataIndex: 'updatedAt',
                label: this.$t('transactions-list.updated_at'),
                sortIsAllowed: true,
                allowResize: true,
            },
            {
                property: 'paynlTransactionId',
                dataIndex: 'paynlTransactionId',
                label: this.$t('transactions-list.paynl_transaction_id'),
                allowResize: true,
                sortIsAllowed: true,
                primary: true
            },
            {
                property: 'order.orderNumber',
                dataIndex: 'orderNumber',
                label: this.$t('transactions-list.order_number'),
                sortIsAllowed: false,
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
                property: 'customer.lastName',
                dataIndex: 'customer',
                label: this.$t('transactions-list.customer'),
                allowResize: true,
                sortIsAllowed: false,
                primary: true
            },
            {
                property: 'status',
                dataIndex: 'status',
                label: this.$t('transactions-list.status'),
                allowResize: true,
                sortIsAllowed: false,
                primary: true
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
        let criteria = new Criteria();
        criteria.addAssociation('order');
        criteria.addAssociation('customer');
        // criteria.addAssociation('transactions');

        this.repository
            .search(criteria, this.context)
            .then((result) => {
                console.log(result);
                this.transactions = result;
            });
    },

    methods: {
        getVariantFromPaymentState(item) {
            return this.stateStyleDataProviderService.getStyle(
                'order_transaction.state', this.statuses[item.stateId]
            ).variant;
        }
    }
});
