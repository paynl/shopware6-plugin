import './components/paynl-plugin-settings';
import './components/paynl-settings-icon';
import './acl';

import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

const { Module } = Shopware;

Module.register('paynl-settings', {
    type: "plugin",
    name: "paynlSettings.general.name",
    title: "paynlSettings.general.mainMenuItemGeneral",
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
                parentPath: 'sw.settings.index',
                privilege: 'paynl.viewer',
            }
        }
    },

    settingsItem: {
        group: 'plugins',
        to: 'paynl.settings.view',
        backgroundEnabled: true,
        iconComponent: 'paynl-settings-icon',
        privilege: 'paynl.viewer',
    }
});
