import './page/refund-page-view';
import './page/components/paynl-refund-products-list';
import './page/components/paynl-refund-single-order-product';

import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

const { Module } = Shopware;

Module.register('refund-page', {
    type: "plugin",
    name: "module.name",
    title: "module.title",
    description: "module.description",
    color: '#62ff80',
    snippets: {
        'de-DE': deDE,
        'en-GB': enGB
    },
    routes: {
        view: {
            component: 'refund-page-view',
            path: 'view/:id',
            props: {
                default: (route) => ({ orderId: route.params.id })
            }
        }
    }
});
