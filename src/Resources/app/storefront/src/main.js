// Import all necessary Storefront plugins and scss files
import ExamplePlugin from './example-plugin/example-plugin.plugin';

// Register them via the existing PluginManager
const PluginManager = window.PluginManager;
PluginManager.register('ExamplePlugin', ExamplePlugin, '[data-example-plugin]');
