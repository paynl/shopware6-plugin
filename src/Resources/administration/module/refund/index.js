import './page/refund-page-view';
import './page/components/refund-page-view-base';
import './page/components/refund-card';

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
            component: 'refund-page-view',
            path: 'view/:id',
            redirect: {
                name: 'refund.page.view.base'
            },
            children: {
                base: {
                    component: 'refund-page-view-base',
                    path: 'base',
                    meta: {
                        parentPath: 'sw.order.index'
                    }
                }
            }
        }
    }
});
