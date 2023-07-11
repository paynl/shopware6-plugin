import './page/components/paynl-transactions-list';
import './page/paynl-transactions-list-component';
import './components/paynl-config-section-api';
import './acl';

import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';
import nlNL from './snippet/nl-NL.json';

const { Module } = Shopware;

Module.register('paynl-payment-module', {
    type: "plugin",
    name: "module.name",
    title: "module.title",
    description: "module.description",
    color: '#23ac70',
    snippets: {
        'de-DE': deDE,
        'en-GB': enGB,
        'nl-NL': nlNL
    },
    routes: {
        list: {
            component: 'paynl-transactions-list-component',
            path: 'list',
            meta: {
                privilege: 'paynl_transactions.viewer'
            }
        }
    },
    navigation: [{
        parent: 'sw-order',
        label: "module.navigation.label",
        path: 'paynl.payment.module.list',
        privilege: 'paynl_transactions.viewer',
    }]
});
