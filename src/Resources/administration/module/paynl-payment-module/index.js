import './page/components/transactions-list';
import './page/transactions-list-component';
import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

const { Module } = Shopware;

Module.register('paynl-payment-module', {
    type: "plugin",
    name: "module.name",
    title: "module.title",
    description: "module.description",
    color: '#62ff80',
    icon: 'default-money-card',
    snippets: {
        'de-DE': deDE,
        'en-GB': enGB
    },
    routes: {
        list: {
            component: 'transactions-list-component',
            path: 'list'
        }
    },
    navigation: [{
        label: "module.navigation.label",
        color: '#62ff80',
        path: 'paynl.payment.module.list',
        icon: 'default-money-card'
    }]
});
