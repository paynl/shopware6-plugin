import './page/refund-view/';
import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

const { Module } = Shopware;

Module.register('refund-page', {
    type: 'plugin',
    name: 'Refund payment',
    title: 'Refund payment module',
    color: '#62ff80',
    snippets: {
        'de-DE': deDE,
        'en-GB': enGB
    },
    routes: {
        view: {
            component: 'refund-view',
            path: 'view/:id'
        }
    }
});
