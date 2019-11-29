import template from './transactions-list.html.twig';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

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
            transactions: null
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
        criteria.addAssociation('orderStateMachine');

        this.repository
            .search(criteria, this.context)
            .then((result) => {
                this.transactions = result;
            });
    },

    methods: {
        getVariantFromPaymentState(technicalName) {
            return this.stateStyleDataProviderService.getStyle(
                'order_transaction.state', technicalName
            ).variant;
        },

        getData(date) {
            let dateObj = new Date(date);

            let year = dateObj.getFullYear();
            if (year < 10) { year = '0' + year; }

            let month = dateObj.getMonth();
            if (month < 10) { month = '0' + month; }

            let day = dateObj.getDay();
            if (day < 10) { day = '0' + day; }

            let hours = dateObj.getHours();
            if (hours < 10) { hours = '0' + hours; }

            let minutes = dateObj.getMinutes();
            if (minutes < 10) { minutes = '0' + minutes; }

            return year + '-' + month + '-' + day + ' ' + hours + ':' + minutes;
        }
    }
});
