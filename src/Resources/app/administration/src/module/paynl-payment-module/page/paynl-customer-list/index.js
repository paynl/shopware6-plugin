import template from './sw-customer-list.html.twig';
import './sw-customer-list.scss';

/**
 * @package customer-order
 */

const { Mixin, Component } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('paynl-customer-list', {
    template,

    inject: ['repositoryFactory', 'acl', 'filterFactory', 'feature', 'stateStyleDataProviderService'],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('salutation'),
        Mixin.getByName('listing'),
    ],

    data() {
        return {
            customers: null,
            sortBy: 'customerNumber',
            naturalSorting: true,
            sortDirection: 'DESC',
            isLoading: false,
            showDeleteModal: false,
            filterLoading: false,
            availableAffiliateCodes: [],
            availableCampaignCodes: [],
            filterCriteria: [],
            defaultFilters: [
                'affiliate-code-filter',
                'campaign-code-filter',
                'customer-group-request-filter',
                'salutation-filter',
                'account-status-filter',
                'default-payment-method-filter',
                'group-filter',
                'billing-address-country-filter',
                'shipping-address-country-filter',
                'tags-filter',
            ],
            storeKey: 'grid.filter.customer',
            activeFilterNumber: 0,
            searchConfigEntity: 'customer',
            showBulkEditModal: false,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        customerRepository() {
            return this.repositoryFactory.create('paynl_transactions');
        },

        customerColumns() {
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
            return this.getCustomerColumns();
        },

        defaultCriteria() {
            const defaultCriteria = new Criteria(this.page, this.limit);
            this.naturalSorting = this.sortBy === 'customerNumber';

            defaultCriteria.setTerm(this.term);

            this.sortBy.split(',').forEach(sortBy => {
                defaultCriteria.addSorting(Criteria.sort(sortBy, this.sortDirection, this.naturalSorting));
            });

            defaultCriteria
                .addAssociation('defaultBillingAddress')
                .addAssociation('group')
                .addAssociation('requestedGroup')
                .addAssociation('salesChannel');

            this.filterCriteria.forEach(filter => {
                defaultCriteria.addFilter(filter);
            });

            return defaultCriteria;
        },

        filterSelectCriteria() {
            const criteria = new Criteria(1, 1);
            criteria.addFilter(Criteria.not(
                'AND',
                [Criteria.equals('affiliateCode', null), Criteria.equals('campaignCode', null)],
            ));
            criteria.addAggregation(Criteria.terms('affiliateCodes', 'affiliateCode', null, null, null));
            criteria.addAggregation(Criteria.terms('campaignCodes', 'campaignCode', null, null, null));

            return criteria;
        },

        listFilterOptions() {
            return {
                'affiliate-code-filter': {
                    property: 'affiliateCode',
                    type: 'multi-select-filter',
                    label: this.$tc('sw-customer.filter.affiliateCode.label'),
                    placeholder: this.$tc('sw-customer.filter.affiliateCode.placeholder'),
                    valueProperty: 'key',
                    labelProperty: 'key',
                    options: this.availableAffiliateCodes,
                },
                'campaign-code-filter': {
                    property: 'campaignCode',
                    type: 'multi-select-filter',
                    label: this.$tc('sw-customer.filter.campaignCode.label'),
                    placeholder: this.$tc('sw-customer.filter.campaignCode.placeholder'),
                    valueProperty: 'key',
                    labelProperty: 'key',
                    options: this.availableCampaignCodes,
                },
                'customer-group-request-filter': {
                    property: 'requestedGroupId',
                    type: 'existence-filter',
                    label: this.$tc('sw-customer.filter.customerGroupRequest.label'),
                    placeholder: this.$tc('sw-customer.filter.customerGroupRequest.placeholder'),
                    optionHasCriteria: this.$tc('sw-customer.filter.customerGroupRequest.textHasCriteria'),
                    optionNoCriteria: this.$tc('sw-customer.filter.customerGroupRequest.textNoCriteria'),
                },
                'salutation-filter': {
                    property: 'salutation',
                    label: this.$tc('sw-customer.filter.salutation.label'),
                    placeholder: this.$tc('sw-customer.filter.salutation.placeholder'),
                    labelProperty: 'displayName',
                },
                'account-status-filter': {
                    property: 'active',
                    label: this.$tc('sw-customer.filter.status.label'),
                    placeholder: this.$tc('sw-customer.filter.status.placeholder'),
                },
                'default-payment-method-filter': {
                    property: 'defaultPaymentMethod',
                    label: this.$tc('sw-customer.filter.defaultPaymentMethod.label'),
                    placeholder: this.$tc('sw-customer.filter.defaultPaymentMethod.placeholder'),
                },
                'group-filter': {
                    property: 'group',
                    label: this.$tc('sw-customer.filter.customerGroup.label'),
                    placeholder: this.$tc('sw-customer.filter.customerGroup.placeholder'),
                },
                'billing-address-country-filter': {
                    property: 'defaultBillingAddress.country',
                    label: this.$tc('sw-customer.filter.billingCountry.label'),
                    placeholder: this.$tc('sw-customer.filter.billingCountry.placeholder'),
                },
                'shipping-address-country-filter': {
                    property: 'defaultShippingAddress.country',
                    label: this.$tc('sw-customer.filter.shippingCountry.label'),
                    placeholder: this.$tc('sw-customer.filter.shippingCountry.placeholder'),
                },
                'tags-filter': {
                    property: 'tags',
                    label: this.$tc('sw-customer.filter.tags.label'),
                    placeholder: this.$tc('sw-customer.filter.tags.placeholder'),
                },
            };
        },

        listFilters() {
            return this.filterFactory.create('customer', this.listFilterOptions);
        },
    },

    watch: {
        defaultCriteria: {
            handler() {
                this.getList();
            },
            deep: true,
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            return this.loadFilterValues();
        },

        onInlineEditSave(promise, customer) {
            promise.then(() => {
                this.createNotificationSuccess({
                    message: this.$tc('sw-customer.detail.messageSaveSuccess', 0, { name: this.salutation(customer) }),
                });
            }).catch(() => {
                this.getList();
                this.createNotificationError({
                    message: this.$tc('sw-customer.detail.messageSaveError'),
                });
            });
        },

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
                const items = await this.customerRepository.search(criteria);

                this.total = items.total;
                this.customers = items;
                this.isLoading = false;
                this.selection = {};
            } catch {
                this.isLoading = false;
            }
        },

        onDelete(id) {
            this.showDeleteModal = id;
        },

        onCloseDeleteModal() {
            this.showDeleteModal = false;
        },

        onConfirmDelete(id) {
            this.showDeleteModal = false;

            return this.customerRepository.delete(id).then(() => {
                this.getList();
            });
        },

        async onChangeLanguage() {
            await this.createdComponent();
            await this.getList();
        },

        getCustomerColumns() {
            const columns = [{
                property: 'firstName',
                dataIndex: 'lastName,firstName',
                inlineEdit: 'string',
                label: 'sw-customer.list.columnName',
                routerLink: 'sw.customer.detail',
                width: '250px',
                allowResize: true,
                primary: true,
                useCustomSort: true,
            }, {
                property: 'defaultBillingAddress.street',
                label: 'sw-customer.list.columnStreet',
                allowResize: true,
                useCustomSort: true,
            }, {
                property: 'defaultBillingAddress.zipcode',
                label: 'sw-customer.list.columnZip',
                align: 'right',
                allowResize: true,
                useCustomSort: true,
            }, {
                property: 'defaultBillingAddress.city',
                label: 'sw-customer.list.columnCity',
                allowResize: true,
                useCustomSort: true,
            }, {
                property: 'customerNumber',
                dataIndex: 'customerNumber',
                naturalSorting: true,
                label: 'sw-customer.list.columnCustomerNumber',
                allowResize: true,
                inlineEdit: 'string',
                align: 'right',
                useCustomSort: true,
            }, {
                property: 'group',
                dataIndex: 'group',
                naturalSorting: true,
                label: 'sw-customer.list.columnGroup',
                allowResize: true,
                inlineEdit: 'string',
                align: 'right',
                useCustomSort: true,
            }, {
                property: 'email',
                inlineEdit: 'string',
                label: 'sw-customer.list.columnEmail',
                allowResize: true,
                useCustomSort: true,
            }, {
                property: 'affiliateCode',
                inlineEdit: 'string',
                label: 'sw-customer.list.columnAffiliateCode',
                allowResize: true,
                visible: false,
                useCustomSort: true,
            }, {
                property: 'campaignCode',
                inlineEdit: 'string',
                label: 'sw-customer.list.columnCampaignCode',
                allowResize: true,
                visible: false,
                useCustomSort: true,
            }, {
                property: 'boundSalesChannelId',
                label: 'sw-customer.list.columnBoundSalesChannel',
                allowResize: true,
                visible: false,
                useCustomSort: true,
            }, {
                property: 'active',
                inlineEdit: 'boolean',
                label: 'sw-customer.list.columnActive',
                allowResize: true,
                visible: false,
                useCustomSort: true,
            }];

            return columns;
        },

        loadFilterValues() {
            this.filterLoading = true;

            return this.customerRepository.search(this.filterSelectCriteria)
                .then(({ aggregations }) => {
                    this.availableAffiliateCodes = aggregations?.affiliateCodes?.buckets ?? [];
                    this.availableCampaignCodes = aggregations?.campaignCodes?.buckets ?? [];
                    this.filterLoading = false;

                    return aggregations;
                }).catch(() => {
                    this.filterLoading = false;
                });
        },

        updateCriteria(criteria) {
            this.page = 1;
            this.filterCriteria = criteria;
        },

        async onBulkEditItems() {
            await this.$nextTick();
            this.$router.push({ name: 'sw.bulk.edit.customer' });
        },

        onBulkEditModalOpen() {
            this.showBulkEditModal = true;
        },

        onBulkEditModalClose() {
            this.showBulkEditModal = false;
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
