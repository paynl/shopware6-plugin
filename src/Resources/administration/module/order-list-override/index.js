import template from "./index.html.twig";
import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

const { Component } = Shopware;

Component.override('sw-order-list', {
    template
});
