import { Application } from 'src/core/shopware';
import ApiService from 'src/core/service/api.service';

class PaynlPaymentService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'paynl') {
        super(httpClient, loginService, apiEndpoint);
    }

    installPaymentMethods() {
        const headers = this.getBasicHeaders();
        return this.httpClient
            .get(`${this.getApiBasePath()}/install-payment-methods`, {headers: headers})
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
}

Application.addServiceProvider('PaynlPaymentService', (container) => {
    const initContainer = Application.getContainer('init');

    return new PaynlPaymentService(initContainer.httpClient, container.loginService);
});

