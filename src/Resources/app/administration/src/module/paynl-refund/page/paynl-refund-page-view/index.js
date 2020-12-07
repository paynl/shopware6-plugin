import template from './paynl-refund-page-view.html.twig';
import './paynl-refund-card.scss';

const {Component, Mixin} = Shopware;
const {Criteria} = Shopware.Data;

Component.register('paynl-refund-page-view', {
    template,

    mixins: [
        Mixin.getByName('notification')
    ],

    inject: [
        'repositoryFactory',
        'PaynlPaymentService'
    ],

    props: {
        orderId: {
            type: String,
            required: false,
            default: null
        }
    },

    data() {
        return {
            paynlTransactionRepository: null,
            orderRepository: null,
            order: null,
            paynlTransaction: null,

            availableForRefund: 0,
            refundedAmount: 0,

            amountToRefund: 0,
            products: null,
            identifier: '',
            description: '',
            productsQuantity: {},
            withShipping: false,

            isEditing: false,
            isLoading: false,
            isSaveSuccessful: false,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier)
        };
    },

    created() {
        this.createdComponent();
    },

    computed: {
        fullName() {
            return this.order.orderCustomer.firstName + ' ' + this.order.orderCustomer.lastName;
        },
    },

    methods: {
        getDataForRefund() {
            this.PaynlPaymentService.getRefundData(this.paynlTransaction.paynlTransactionId)
                .then((data) => {
                    if (data.errorMessage) {
                        this.createNotificationError({
                            title: this.$tc('refund.notifications.danger'),
                            message: data.errorMessage
                        });
                    } else {
                        this.availableForRefund = data.availableForRefund;
                        this.refundedAmount = data.refundedAmount;

                        if (this.withShipping) {
                            this.amountToRefund = this.availableForRefund;
                        } else {
                            this.amountToRefund = this.availableForRefund - this.order.shippingTotal;
                        }

                        this.isLoading = false;
                    }
                    this.isLoading = false;
                })
                .catch((exception) => {
                    this.isLoading = false;
                });
        },

        createdComponent() {
            this.loadOrderData();
        },

        updateIdentifier(identifier) {
            this.identifier = identifier;
        },

        updateShippingState(value) {
            if (value) {
                this.amountToRefund += this.order.shippingTotal;
            } else {
                this.amountToRefund -= this.order.shippingTotal;
            }
        },

        changeRefundAmount(amount) {
            let newAmount = amount;
            if (this.withShipping) {
                newAmount += this.order.shippingTotal;
            }
            this.amountToRefund = newAmount;
        },

        onRefundClick() {
            this.isLoading = true;

            let data = {
                transactionId: this.paynlTransaction.paynlTransactionId,
                amount: this.amountToRefund,
                description: this.description,
                products: this.products
            };

            this.PaynlPaymentService.refundTransaction(data)
                .then((responseData) => {
                    if (responseData[0].type === 'danger') {
                        this.createNotificationError({
                            title: this.$tc('refund.notifications.danger'),
                            message: responseData[0].content
                        });
                    } else if (responseData[0].type === 'success') {
                        this.createNotificationSuccess({
                            title: this.$tc('refund.notifications.success'),
                            message: responseData[0].content
                        });
                    }
                    this.createdComponent();
                })
                .catch((exception) => {
                    this.isLoading = false;

                    this.createNotificationError({
                        title: this.$tc('refund.notifications.danger'),
                        message: exception
                    });
                });
        },

        onUpdateLoading(loadingValue) {
            this.isLoading = loadingValue;
        },

        loadOrderData() {
            this.isLoading = true;
            let criteria = new Criteria();
            criteria
                .addAssociation('paynlTransactions')
                .addAssociation('currency')
                .addAssociation('lineItems');

            criteria.getAssociation('paynlTransactions')
                .addSorting(Criteria.sort('paynl_transactions.createdAt', 'DESC'));

            this.orderRepository = this.repositoryFactory.create('order');
            this.orderRepository.get(this.orderId, Shopware.Context.api, criteria)
                .then((response) => {
                    this.order = response;
                    this.paynlTransaction = response.extensions.paynlTransactions[0];
                    this.products = this.order.lineItems;

                    this.getDataForRefund();
                });
        }
    }
});
