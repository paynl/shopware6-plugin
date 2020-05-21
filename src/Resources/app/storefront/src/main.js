import PaynlPaymentPlugin from './paynl-payment-plugin/paynl-payment-plugin.plugin';

const PluginManager = window.PluginManager;
PluginManager.register('PaynlPaymentPlugin', PaynlPaymentPlugin, '[paynl-payment-plugin]');