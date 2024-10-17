import PaynlPaymentPlugin from './paynl-payment-plugin/paynl-payment-plugin.plugin';
import PaynlKvkCocFieldTogglePlugin from './paynl-payment-plugin/paynl-kvk-coc-field-toggle.plugin';
import PaynlDatePickerPlugin from './paynl-payment-plugin/paynl-date-picker.plugin';
import PaynlPayPalExpressButton from './paynl-payment-plugin/paynl-paypal.express-checkout.plugin';

const PluginManager = window.PluginManager;
PluginManager.register('PaynlDatePicker', PaynlDatePickerPlugin, '[data-paynl-date-picker]');
PluginManager.register('PaynlPaymentPlugin', PaynlPaymentPlugin, '[data-paynl-payment-plugin]');
PluginManager.register('PaynlKvkCocFieldTogglePlugin', PaynlKvkCocFieldTogglePlugin, '.country-select, .contact-select');
PluginManager.register('PaynlPayPalExpressButton', PaynlPayPalExpressButton, '[data-paynl-paypal-express-button]');
