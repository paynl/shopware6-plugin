// Import all necessary Storefront plugins and scss files
import ExamplePlugin from './example-plugin/example-plugin.plugin';
import PaynlCocPlugin from './paynl-cocfield-plugin/paynl-cocfield.plugin';
import PaynlSavebtnPlugin from './paynl-cocfield-plugin/paynl-cocfield.plugin';

// Register them via the existing PluginManager
const PluginManager = window.PluginManager;
PluginManager.register('ExamplePlugin', ExamplePlugin, '[data-example-plugin]');
PluginManager.register('PaynlCocPlugin', PaynlCocPlugin, '[paynl-coc-plugin]');
PluginManager.register('PaynlSavebtnPlugin', PaynlSavebtnPlugin, '[paynl-savebtn-plugin]');
