import Plugin from 'src/plugin-system/plugin.class';

export default class PaynlKvkCocFieldTogglePlugin extends Plugin {
    init() {
        const countrySelect = document.querySelector('.paynl-country-select');
        if (countrySelect !== null) {
            countrySelect.onchange = this.onCountryChange;
        }

        const accountTypeSelect = document.querySelector('#addressaccountType');
        if (accountTypeSelect !== null) {
            accountTypeSelect.onchange = this.onAccountTypeChange;
        }
    }

    onCountryChange(event) {
        const selectedOption = event.target.options[event.target.selectedIndex];
        const kvkCocFieldBlock = document.getElementById('paynl-kvk-coc-number-field');

        if (selectedOption.getAttribute('data-paynl-kvk-coc-field') === null) {
            kvkCocFieldBlock.style.display = 'none';

            return;
        }

        const accountTypeSelect = document.querySelector('#addressaccountType');
        if (accountTypeSelect === null || accountTypeSelect.value !== 'business') {
            kvkCocFieldBlock.style.display = 'none';

            return;
        }

        kvkCocFieldBlock.style.display = 'inline-block';
    }

    onAccountTypeChange(event) {
        const kvkCocFieldBlock = document.getElementById('paynl-kvk-coc-number-field');
        if (event.target.value !== 'business') {
            kvkCocFieldBlock.style.display = 'none';

            return;
        }

        const countrySelect = document.querySelector('.paynl-country-select');

        if (countrySelect === null) {
            return;
        }
        const selectedValue = countrySelect.options[countrySelect.selectedIndex];

        if (selectedValue.getAttribute('data-paynl-kvk-coc-field') === null) {
            kvkCocFieldBlock.style.display = 'none';

            return;
        }

        kvkCocFieldBlock.style.display = 'inline-block';
    }
}
