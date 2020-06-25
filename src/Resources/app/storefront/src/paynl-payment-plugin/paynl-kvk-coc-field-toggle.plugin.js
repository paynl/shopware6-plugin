import Plugin from 'src/plugin-system/plugin.class';

export default class PaynlKvkCocFieldTogglePlugin extends Plugin {
    init() {
        const countrySelect = document.getElementsByClassName('paynl-country-select');
        for (let i = 0; i < countrySelect.length; i++) {
            countrySelect[i].onchange = this.onCountryChangeCallback;
        }
    }

    onCountryChangeCallback(event) {
        const selectedCountryKvkCocField = event.target.selectedOptions[0].dataset.paynlKvkCocField;
        const kvkCocFieldBlock = document.getElementById('paynl-kvk-coc-number-field');
        kvkCocFieldBlock.style.display = 'none';
        if (selectedCountryKvkCocField === 'true') {
            kvkCocFieldBlock.style.display = 'inline-block';
        }
    }
}
