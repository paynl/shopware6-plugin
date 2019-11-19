import './page/components/data-grid';
import './page/components/transactions-list';
import './page/transactions-list-component';
import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

const { Module } = Shopware;

Module.register('paynl-payment-module', {
    type: 'plugin',
    name: 'Paynl payment',
    title: 'Paynl payment module',
    description: 'Paynl payment description',
    color: '#62ff80',
    icon: 'default-object-lab-flask',
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
        label: 'Paynl transactions',
        color: '#62ff80',
        path: 'paynl.payment.module.list',
        icon: 'default-object-lab-flask'
    }]
});
