import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

Shopware.Module.register('paynl-transactions-module', {
    type: 'plugin',
    name: 'Paynl',
    title: 'Paynl transactions module',
    description: 'Description for Paynl transactions module',
    color: '#62ff80',
    icon: 'default-object-lab-flask',

    snippets: {
        'de-DE': deDE,
        'en-GB': enGB
    },

    routes: {
        overview: {
            component: 'sw-product-list',
            path: 'overview'
        }
    },

    navigation: [{
        label: 'Paynl transactions module',
        color: '#62ff80',
        path: 'paynl.module.overview',
        icon: 'default-object-lab-flask'
    }]
});
