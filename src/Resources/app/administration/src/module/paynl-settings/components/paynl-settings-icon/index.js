import template from './paynl-settings-icon.html.twig';
import './paynl-settings-icon.scss';

const { Component } = Shopware;

Component.register('paynl-settings-icon', {
    template,
    computed: {
        assetFilter() {
            return Shopware.Filter.getByName('asset');
        },
    }
});
