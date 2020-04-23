import './components/paynl-plugin-settings';

import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

const { Module } = Shopware;

Module.register('paynl-settings', {
    type: "plugin",
    name: "paynlConfig.name",
    title: "paynlConfig.title",
    description: "paynlConfig.description",
    color: '#62ff80',
    snippets: {
        'de-DE': deDE,
        'en-GB': enGB
    },

    routeMiddleware(next, currentRoute) {
        next(currentRoute);
    },

    routes: {
        view: {
            component: 'paynl-plugin-settings',
            path: 'index'
        }
    }
});
