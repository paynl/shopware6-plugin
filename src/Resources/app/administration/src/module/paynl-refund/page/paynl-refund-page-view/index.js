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
            refundData: null,
            paynlTransaction: null,
            products: null,
            identifier: '',
            isEditing: false,
            isLoading: true,
            isSaveSuccessful: false,
            amountToRefund: 0,
            description: '',
            productsQuantity: {},
            withShipping: false
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier)
        };
    },

    mounted() {
        this.paynlTransactionRepository = this.repositoryFactory.create('paynl_transactions');
        let criteria = new Criteria();
        criteria.addFilter(Criteria.equals('orderId', this.orderId));
        criteria.addSorting(
            Criteria.sort('paynl_transactions.createdAt', 'DESC')
        );

        this.paynlTransactionRepository.search(criteria, Shopware.Context.api)
            .then((result) => {
                this.paynlTransaction = result[0];

                let criteriaOrder = new Criteria();
                criteriaOrder
                    .addAssociation('currency')
                    .addAssociation('lineItems');

                this.orderRepository = this.repositoryFactory.create('order');
                this.orderRepository.get(this.orderId, Shopware.Context.api, criteriaOrder)
                    .then((response) => {
                        this.order = response;
                        // TODO: modify to use one to one relation
                        this.products = this.order.lineItems;

                        this.getDataForRefund();
                    });
            });
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
                        this.refundData = data;
                        this.amountToRefund = this.refundData.availableForRefund + this.order.shippingTotal;
                        this.isLoading = false;
                    }
                })
                .catch((errorResponse) => {

                });
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
                    location.reload();
                })
                .catch((errorResponse) => {});
        },

        onUpdateLoading(loadingValue) {
            this.isLoading = loadingValue;
        }
    }
});
