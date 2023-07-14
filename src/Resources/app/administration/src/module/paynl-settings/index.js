import './acl';

import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

const { Module } = Shopware;

Shopware.Component.register('paynl-settings-icon', () => import ('./components/paynl-settings-icon'));
Shopware.Component.register('paynl-config-section-api', () => import ('./components/paynl-config-section-api'));
Shopware.Component.register('paynl-plugin-settings', () => import('./page/paynl-plugin-settings'));

Module.register('paynl-settings', {
    type: "plugin",
    name: "paynlSettings.general.name",
    title: "paynlSettings.general.mainMenuItemGeneral",
    description: "paynlSettings.general.description",
    version: '1.0.0',
    targetVersion: '1.0.0',
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
                parentPath: 'sw.settings.index',
                privilege: 'paynl.viewer',
            }
        }
    },

    settingsItem: {
        group: 'plugins',
        to: 'paynl.settings.view',
        backgroundEnabled: false,
        iconComponent: 'paynl-settings-icon',
        privilege: 'paynl.viewer',
    }
});
