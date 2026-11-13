/**
 * Base class of all Pay. storefront plugins.
 *
 * Shopware 6.5, 6.6 and 6.7 expose their plugin base class on window, so the plugins
 * extend the class of the shop they run in instead of a copy bundled at build time.
 */
export default window.PluginBaseClass;
