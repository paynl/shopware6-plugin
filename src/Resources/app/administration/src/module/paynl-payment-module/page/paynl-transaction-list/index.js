import template from './paynl-transaction-list.html.twig';
import './paynl-transaction-list.scss';

const { Mixin, Component } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('paynl-transaction-list', {
    template,

    inject: ['repositoryFactory', 'acl', 'stateStyleDataProviderService'],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('salutation'),
        Mixin.getByName('listing'),
    ],

    data() {
        return {
            transactions: null,
            isLoading: false,
            filterCriteria: [],
            activeFilterNumber: 0,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        transactionRepository() {
            return this.repositoryFactory.create('paynl_transactions');
        },

        transactionColumns() {
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
                }
            ];
        },
    },

    created() {
    },

    methods: {
        async getList() {
            this.isLoading = true;

            if (!this.entitySearchable) {
                this.isLoading = false;
                this.total = 0;

                return;
            }

            let criteria = new Criteria(this.page, this.limit);
            criteria.addAssociation('order');
            criteria.addAssociation('customer');
            criteria.addAssociation('stateMachineState');
            criteria.addSorting(
                Criteria.sort('paynl_transactions.createdAt', 'DESC')
            );

            try {
                const items = await this.transactionRepository.search(criteria);

                this.total = items.total;
                this.transactions = items;
                this.isLoading = false;
                this.selection = {};
            } catch {
                this.isLoading = false;
            }
        },

        updateCriteria(criteria) {
            this.page = 1;
            this.filterCriteria = criteria;
        },

        getVariantFromPaymentState(technicalName) {
            return this.stateStyleDataProviderService.getStyle(
                'order_transaction.state', technicalName
            ).variant;
        },

        getData(date) {
            if (date <= 0) {
                return '';
            }

            let regex = /(?<year>\d{4}).(?<month>\d{2}).(?<day>\d{2}).(?<hours>\d{2}).(?<minutes>\d{2})/gm; //NOSONAR
            let dateGroup = regex.exec(date)['groups'];

            return dateGroup['year'] + '-' + dateGroup['month'] + '-' + dateGroup['day'] + ' ' + dateGroup['hours'] + ':' + dateGroup['minutes'];
        },

        isUserCustomersViewer() {
            return this.acl.can('customer.viewer');
        },

        isUserOrdersViewer() {
            return this.acl.can('order.viewer');
        }
    },
});
