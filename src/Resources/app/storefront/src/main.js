import PaynlPaymentPlugin from './paynl-payment-plugin/paynl-payment-plugin.plugin';
import PaynlKvkCocFieldTogglePlugin from './paynl-payment-plugin/paynl-kvk-coc-field-toggle.plugin';
import PaynlCsePlugin from './paynl-payment-plugin/paynl-cse.plugin';

const PluginManager = window.PluginManager;
PluginManager.register('PaynlPaymentPlugin', PaynlPaymentPlugin, '[data-paynl-payment-plugin]');
PluginManager.register('PaynlKvkCocFieldTogglePlugin', PaynlKvkCocFieldTogglePlugin, '.country-select, .contact-select');
PluginManager.register('PaynlCsePlugin', PaynlCsePlugin, '.paynl-payment-method-cse');
