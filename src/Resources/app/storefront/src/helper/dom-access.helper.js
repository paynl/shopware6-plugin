/**
 * DOM lookups for the Pay. storefront plugins.
 *
 * Behaves like Shopware's DomAccess helper, so the bundle does not depend on
 * Shopware's storefront sources.
 */
export default class DomAccess {
    /**
     * @param {*} element
     * @returns {boolean}
     */
    static isNode(element) {
        if (typeof element !== 'object' || element === null) {
            return false;
        }

        return element === document || element === window || element instanceof Node;
    }

    /**
     * Returns the first element matching the selector.
     * In strict mode it throws when the parent is not a node or nothing matches.
     *
     * @param {Document|Element} parentNode
     * @param {string} selector
     * @param {boolean} strict
     * @returns {Element|false}
     */
    static querySelector(parentNode, selector, strict = true) {
        if (strict && !DomAccess.isNode(parentNode)) {
            throw new Error('The parent node is not a valid HTML Node!');
        }

        const element = parentNode.querySelector(selector) || false;

        if (strict && element === false) {
            throw new Error(`The required element "${selector}" does not exist in parent node!`);
        }

        return element;
    }
}
