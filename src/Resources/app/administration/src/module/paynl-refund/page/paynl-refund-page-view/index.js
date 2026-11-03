import template from './paynl-refund-page-view.html.twig';
import './paynl-refund-card.scss';
import { hasRefundPrivilege } from './../../../../util/refund-permission.util';
import VersionCompare from './../../../../util/version-compare.util';

const { Component, Context, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

const REFUND_DATA_FAILED_SNIPPET = 'paynlValidation.error.refundDataFailed';
const REFUND_FAILED_SNIPPET = 'paynlValidation.error.refundFailed';

Component.register('paynl-refund-page-view', {
    template,

    mixins: [
        Mixin.getByName('notification')
    ],

    inject: [
        'acl',
        'repositoryFactory',
        'PaynlPaymentService',
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
            refundDataError: null,
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
        canRefund() {
            return hasRefundPrivilege(this.acl);
        },
        hasRefundDataError() {
            return this.refundDataError !== null;
        },
        // sw-alert renders mt-banner since 6.7, which renamed the "error" variant to "critical".
        errorAlertVariant() {
            return new VersionCompare().isGreaterOrEqual(Context.app.config.version, '6.7') ? 'critical' : 'error';
        },
        fullName() {
            return this.order.orderCustomer.firstName + ' ' + this.order.orderCustomer.lastName;
        },
        maxRefundAmount() {
            if (this.withShipping) {
                return this.availableForRefund;
            }

            return this.availableForRefund - this.order.shippingTotal;
        },
        getProductsPrice() {
            return this.paynlTransaction.amount - this.order.shippingTotal;
        },
        showHelpText() {
            return (!this.withShipping && this.order.shippingTotal > 0);
        },
        currencyFilter() {
            return Shopware.Filter.getByName('currency');
        }
    },

    methods: {
        getDataForRefund() {
            this.refundDataError = null;

            this.PaynlPaymentService.getRefundData(this.paynlTransaction.paynlTransactionId)
                .then((data) => {
                    this.availableForRefund = data.availableForRefund;
                    this.refundedAmount = data.refundedAmount;

                    this.amountToRefund = this.availableForRefund;
                    if (!this.withShipping) {
                        this.amountToRefund -= this.order.shippingTotal;
                    }
                })
                .catch((exception) => {
                    this.refundDataError = this.getErrorMessage(exception, REFUND_DATA_FAILED_SNIPPET);

                    this.createNotificationError({
                        title: this.$tc('refund.notifications.danger'),
                        message: this.refundDataError
                    });
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        /**
         * The refund routes reject with either { errorMessage } or [{ type, content }], holding the PAY. API
         * message or a snippet key. Anything else (e.g. a missing privilege) gets the fallback snippet.
         */
        getErrorMessage(exception, fallbackSnippet) {
            const data = exception?.response?.data;
            const errorMessage = data?.errorMessage ?? data?.[0]?.content;

            if (typeof errorMessage !== 'string' || errorMessage.trim() === '') {
                return this.$tc(fallbackSnippet);
            }

            return this.$te(errorMessage) ? this.$tc(errorMessage) : errorMessage;
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
                        message: this.getErrorMessage(exception, REFUND_FAILED_SNIPPET)
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
        },

        onCancelRefunding() {
            this.isLoading = true;

            window.location.reload();
        },
    }
});
