import './components/paynl-plugin-settings';
import './components/paynl-settings-icon';

import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

const { Module } = Shopware;

Module.register('paynl-settings', {
    type: "plugin",
    name: "paynlSettings.general.name",
    title: "paynlSettings.general.title",
    description: "paynlSettings.general.description",
    icon: 'default-action-settings',
    color: '#23ac70',
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
            path: 'view',
            meta: {
                parentPath: 'sw.settings.index'
            }
        }
    },

    settingsItem: {
        group: 'plugins',
        to: 'paynl.settings.view',
        iconComponent: 'paynl-settings-icon',
        backgroundEnabled: true,
    },
});
