// Import all necessary Storefront plugins and scss files
import PaynlPaymentPlugin from './paynl-payment-plugin/paynl-payment-plugin.plugin';

// Register them via the existing PluginManager
const PluginManager = window.PluginManager;
PluginManager.register('PaynlPaymentPlugin', PaynlPaymentPlugin, '[paynl-payment-plugin]');
