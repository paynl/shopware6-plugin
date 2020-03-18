const { Application } = Shopware;
const ApiService = Shopware.Classes.ApiService;

class PaynlPaymentService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'paynl') {
        super(httpClient, loginService, apiEndpoint);
    }

    installPaymentMethods() {
        return this.httpClient
            .get(`${this.getApiBasePath()}/install-payment-methods`, {headers: this.getBasicHeaders()})
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    getRefundData(transactionId) {
        return this.httpClient
            .get(`${this.getApiBasePath()}/get-refund-data?transactionId=${transactionId}`, {headers: this.getBasicHeaders()})
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    refundTransaction(data) {
        return this.httpClient
            .post(`${this.getApiBasePath()}/refund`, data, {headers: this.getBasicHeaders()})
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    changeTransactionStatus(data) {
        return this.httpClient
            .post(`${this.getApiBasePath()}/change-transaction-status`, data, {headers: this.getBasicHeaders()})
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
}

Application.addServiceProvider('PaynlPaymentService', (container) => {
    const initContainer = Application.getContainer('init');

    return new PaynlPaymentService(initContainer.httpClient, container.loginService);
});

