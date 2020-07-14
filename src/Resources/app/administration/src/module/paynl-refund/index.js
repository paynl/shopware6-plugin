import './page/paynl-refund-page-view';
import './page/components/paynl-refund-products-list';
import './page/components/paynl-refund-single-order-product';

import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';
import nlNL from './snippet/nl-NL.json';

const { Module } = Shopware;

Module.register('paynl-refund-page', {
    type: "plugin",
    name: "refundModule.name",
    title: "refundModule.title",
    description: "refundModule.description",
    color: '#23ac70',
    snippets: {
        'de-DE': deDE,
        'en-GB': enGB,
        'nl-NL': nlNL
    },
    routes: {
        view: {
            component: 'paynl-refund-page-view',
            path: 'view/:id',
            props: {
                default: (route) => ({ orderId: route.params.id })
            }
        }
    }
});
