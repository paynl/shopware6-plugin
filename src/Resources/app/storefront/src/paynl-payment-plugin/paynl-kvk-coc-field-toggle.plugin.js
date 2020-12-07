import Plugin from 'src/plugin-system/plugin.class';

export default class PaynlKvkCocFieldTogglePlugin extends Plugin {
    init() {
        const countrySelect = document.querySelector('.paynl-country-select');

        const accountTypeSelect = document.querySelector('#accountType');
        if (accountTypeSelect !== null) {
            accountTypeSelect.onchange = this.onAccountTypeChange;
            countrySelect.onchange = this.onCountryChange.bind(null, [accountTypeSelect]);
        }

        const addressAccountTypeSelect = document.querySelector('#addressaccountType');
        if (addressAccountTypeSelect !== null) {
            addressAccountTypeSelect.onchange = this.onAccountTypeChange;
            countrySelect.onchange = this.onCountryChange.bind(null, [addressAccountTypeSelect]);
        }
    }

    onCountryChange(args, event) {
        const select = args[0];
        const selectedOption = event.target.options[event.target.selectedIndex];
        const kvkCocFieldBlock = document.getElementById('paynl-kvk-coc-number-field');

        if (selectedOption.getAttribute('data-paynl-kvk-coc-field') === null) {
            kvkCocFieldBlock.style.display = 'none';

            return;
        }

        if (select === null || select.value !== 'business') {
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
