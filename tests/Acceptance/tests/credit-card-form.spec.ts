import { expect, test, clearDelayedCache } from '@shopware-ag/acceptance-test-suite';
import type { FixtureTypes, IdProvider, Page } from '@shopware-ag/acceptance-test-suite';
import { preparePayPartsCardCheckout } from '../src/shopware-admin';
import { acceptCookies } from '../src/storefront';

type AdminApi = FixtureTypes['AdminApiContext'];

const STOREFRONT_TYPE_ID = '8a243080f92e4c719546314b577cf82b';
const SDK_READY_TIMEOUT_MS = 60_000;

const CARD_BRANDS = [
    'postepay',
    'carte-bleue-high-risk',
    'maestro',
    'amex',
    'dankort',
    'nexi',
    'mastercard',
    'visa',
] as const;

const ADDRESS_BY_COUNTRY: Record<string, { street: string; zipcode: string; city: string }> = {
    BE: { street: 'Rue de la Loi 16', zipcode: '1000', city: 'Brussels' },
    DE: { street: 'Ebbinghoff 10', zipcode: '48624', city: 'Schöppingen' },
    FR: { street: '10 Rue de Rivoli', zipcode: '75001', city: 'Paris' },
    GB: { street: '10 Downing Street', zipcode: 'SW1A 2AA', city: 'London' },
    NL: { street: 'Damrak 1', zipcode: '1012 LG', city: 'Amsterdam' },
    US: { street: '1600 Pennsylvania Avenue NW', zipcode: '20500', city: 'Washington' },
};

interface SearchResponse<T> {
    data: T[];
}

interface EntityResponse<T> {
    data: T;
}

interface DomainRecord {
    url: string;
    salesChannelId: string;
    languageId: string;
    currencyId: string;
}

interface SalesChannelRecord {
    id: string;
    name?: string;
    typeId?: string;
    countryId: string;
    languageId: string;
    currencyId: string;
    customerGroupId: string;
    navigationCategoryId: string;
    paymentMethodId: string;
}

interface CountryRecord {
    id: string;
    iso: string;
    forceStateInRegistration?: boolean;
}

interface StorefrontShop {
    salesChannelId: string;
    languageId: string;
    currencyId: string;
    countryId: string;
    customerGroupId: string;
    navigationCategoryId: string;
    paymentMethodId: string;
    countryIso: string;
    countryStateId?: string;
}

interface Shopper {
    id: string;
    email: string;
    password: string;
}

test.skip('Pay.Parts credit card form is shown on checkout confirm', async ({
    browser,
    AdminApiContext,
    IdProvider,
}) => {
    const shop = await findStorefrontShop(AdminApiContext);
    const cardMethod = await preparePayPartsCardCheckout(AdminApiContext, shop.salesChannelId);
    const shopper = await createShopper(AdminApiContext, IdProvider, {
        ...shop,
        paymentMethodId: cardMethod.id,
    });
    let productId: string | undefined;

    try {
        productId = await createProduct(AdminApiContext, IdProvider, shop);

        const context = await browser.newContext({
            baseURL: process.env.APP_URL,
            ignoreHTTPSErrors: true,
        });
        const appUrl = process.env.APP_URL ?? '';
        await context.route('**/*', async (route) => {
            if (!route.request().url().startsWith(appUrl)) {
                await route.continue();
                return;
            }

            await route.continue({
                headers: {
                    ...route.request().headers(),
                    'ngrok-skip-browser-warning': 'true',
                },
            });
        });
        const page = await context.newPage();

        try {
            await openCheckoutConfirm(page, shopper, productId);
            await expectCreditCardForm(page);
        } finally {
            await context.close();
        }
    } finally {
        if (productId !== undefined) {
            await AdminApiContext.delete(`product/${productId}`);
        }

        await AdminApiContext.delete(`customer/${shopper.id}`);
    }
});

async function openCheckoutConfirm(page: Page, shopper: Shopper, productId: string): Promise<void> {
    await page.goto('account/login');
    await acceptCookies(page);
    await page.locator('#loginMail').fill(shopper.email);
    await page.locator('#loginPassword').fill(shopper.password);
    await page.locator('.login-form button[type="submit"]').click();
    await expect(page.locator('#loginMail')).toBeHidden();

    await page.goto(`detail/${productId}`);
    await page.locator('.buy-widget .btn-buy').click();
    await page.locator('a.begin-checkout-btn').click();
    await page.waitForURL('**/checkout/confirm**');
}

async function expectCreditCardForm(page: Page): Promise<void> {
    const wrapper = page.locator('.paynl-payparts-card-wrapper');
    await expect(wrapper).toBeVisible();

    const gate = wrapper.locator('.payparts-cc-gate__ctp[data-cc-ctp-view]');
    const sdkError = wrapper.locator('.paynl-payparts-card-error');
    await waitForPayPartsSdk(page);

    // The storefront plugin mounts as soon as the session returns. On a cold load that
    // can be before the SDK module has evaluated, so reload once the script is available.
    if (await sdkError.isVisible()) {
        await page.reload();
        await acceptCookies(page);
        await waitForPayPartsSdk(page);
    }

    await expect(gate, (await sdkError.textContent()) ?? 'Pay.Parts card did not render').toBeVisible({
        timeout: SDK_READY_TIMEOUT_MS,
    });
    await expect(sdkError).toBeHidden();
    await acceptCookies(page);
    await expect(gate.locator('[data-cc-ctp-slot].payparts-click-to-pay--expanded')).toBeVisible();
    await expect(gate.locator('.payparts-ctp-wrapper[data-method-id="1"]')).toBeVisible();

    const clickToPayForm = gate.locator('#click-to-pay-form');
    await expect(clickToPayForm).toHaveClass(/\bis-hidden\b/);
    await expect(clickToPayForm).toHaveClass(/\bhidden\b/);
    await expect(clickToPayForm).toBeHidden();

    const savedCards = gate.locator('#click-to-pay-cards');
    await expect(savedCards).toHaveClass(/\bis-hidden\b/);
    await expect(savedCards).toBeHidden();

    await expect(gate.locator('.payparts-ctp-empty-text')).toHaveText(/No cards found for .+@.+\./);
    await expect(gate.getByText('Or', { exact: true })).toBeVisible();

    const differentEmailButton = gate.locator('#click-to-pay-retry-email');
    await expect(differentEmailButton).toContainText('Use a different e-mail address');
    await expect(differentEmailButton).toBeEnabled();
    await differentEmailButton.click({ trial: true });

    const manualEntryButton = gate.locator('button.cc-pay-button-alternate[data-cc-use-card]');
    await expect(manualEntryButton).toContainText('Enter card details manually');
    await expect(manualEntryButton).toBeEnabled();
    await manualEntryButton.click({ trial: true });
    await manualEntryButton.click();

    const form = wrapper.locator('form.cc-form');
    await expect(form).toBeVisible();
    await expect(form).toHaveAttribute('novalidate', '');

    await expect(form.locator('.checkout-label').filter({ hasText: 'Cardholder name' })).toBeVisible();
    const cardholderName = form.locator('input[data-cc-name][name="cc-name"]');
    await expect(cardholderName).toBeVisible();
    await expect(cardholderName).toHaveAttribute('placeholder', 'Full name on card');
    await expect(cardholderName).toHaveAttribute('autocomplete', 'cc-name');

    await expect(form.locator('.checkout-label').filter({ hasText: 'Card number' })).toBeVisible();
    const cardNumber = form.locator('input[data-cc-number][name="cc-number"]');
    await expect(cardNumber).toBeVisible();
    await expect(cardNumber).toHaveAttribute('placeholder', '1234 5678 9012 3456');
    await expect(cardNumber).toHaveAttribute('maxlength', '19');
    await expect(cardNumber).toHaveAttribute('inputmode', 'numeric');

    for (const brand of CARD_BRANDS) {
        await expect(form.locator(`img.cc-brand-icon[data-brand="${brand}"]`)).toBeAttached();
    }

    await expect(form.locator('.checkout-label').filter({ hasText: 'Expiry date' })).toBeVisible();
    const expiry = form.locator('input[data-cc-expiry][name="cc-exp"]');
    await expect(expiry).toBeVisible();
    await expect(expiry).toHaveAttribute('placeholder', 'MM / YY');
    await expect(expiry).toHaveAttribute('maxlength', '9');

    await expect(form.locator('.checkout-label').filter({ hasText: 'CVC' })).toBeVisible();
    const cvc = form.locator('input[data-cc-cvc][name="cc-csc"]');
    await expect(cvc).toBeVisible();
    await expect(cvc).toHaveAttribute('placeholder', 'CVC');
    await expect(cvc).toHaveAttribute('maxlength', '4');

    const payButton = form.locator('#cc-submit-btn[data-cc-pay]');
    await expect(payButton).toBeVisible();
    await expect(payButton).toHaveClass(/cc-pay-disabled/);
    await expect(payButton).toContainText('Pay');
    await expect(form.locator('[data-cc-spinner]')).toBeAttached();
}

async function waitForPayPartsSdk(page: Page): Promise<void> {
    await page.waitForFunction(() => {
        const sdk = (window as Window & { PayPartsSDK?: unknown }).PayPartsSDK;

        return sdk !== undefined || document.querySelector('.payparts-cc-gate__ctp') !== null;
    }, undefined, { timeout: SDK_READY_TIMEOUT_MS });
}

async function findStorefrontShop(adminApi: AdminApi): Promise<StorefrontShop> {
    const appUrl = normalizeBaseUrl(requiredEnv('APP_URL'));
    const domainResponse = await adminApi.post('search/sales-channel-domain', {
        data: { limit: 50 },
    });
    const domains = await readJson<SearchResponse<DomainRecord>>(domainResponse, 'sales channel domains');
    const rootDomains = domains.data.filter((domain) => urlPath(domain.url) === '/');
    const exactDomain = rootDomains.find((domain) => normalizeBaseUrl(domain.url) === appUrl);
    const domain = exactDomain ?? (rootDomains.length === 1 ? rootDomains[0] : undefined);

    if (domain === undefined) {
        const found = domains.data.map((entry) => entry.url).join(', ') || 'none';
        throw new Error(`No storefront domain matches ${appUrl}. Found: ${found}`);
    }

    const channelResponse = await adminApi.get(`sales-channel/${domain.salesChannelId}`);
    const channel = await readJson<EntityResponse<SalesChannelRecord>>(channelResponse, 'sales channel');

    if (channel.data.typeId !== undefined && channel.data.typeId !== STOREFRONT_TYPE_ID) {
        throw new Error(`Domain ${domain.url} is not a storefront sales channel.`);
    }

    const countryResponse = await adminApi.get(`country/${channel.data.countryId}`);
    const country = await readJson<EntityResponse<CountryRecord>>(countryResponse, 'country');
    const countryStateId = country.data.forceStateInRegistration
        ? await firstCountryStateId(adminApi, country.data.id)
        : undefined;

    return {
        salesChannelId: channel.data.id,
        languageId: domain.languageId || channel.data.languageId,
        currencyId: domain.currencyId || channel.data.currencyId,
        countryId: channel.data.countryId,
        customerGroupId: channel.data.customerGroupId,
        navigationCategoryId: channel.data.navigationCategoryId,
        paymentMethodId: channel.data.paymentMethodId,
        countryIso: country.data.iso,
        countryStateId,
    };
}

async function createShopper(
    adminApi: AdminApi,
    idProvider: IdProvider,
    shop: StorefrontShop,
): Promise<Shopper> {
    const { id, uuid } = idProvider.getIdPair();
    const address = ADDRESS_BY_COUNTRY[shop.countryIso];

    if (address === undefined) {
        throw new Error(`No checkout address is defined for country ${shop.countryIso}.`);
    }

    const salutationResponse = await adminApi.post('search/salutation', {
        data: { limit: 1 },
    });
    const salutations = await readJson<SearchResponse<{ id: string }>>(salutationResponse, 'salutation');
    const salutationId = salutations.data[0]?.id;

    if (salutationId === undefined) {
        throw new Error('No salutation is available for the checkout customer.');
    }

    const email = `payparts-${id}@example.com`;
    const password = 'shopware';
    const billingAddress = {
        firstName: 'Pay',
        lastName: 'Parts',
        city: address.city,
        street: address.street,
        zipcode: address.zipcode,
        countryId: shop.countryId,
        salutationId,
        ...(shop.countryStateId ? { countryStateId: shop.countryStateId } : {}),
    };

    const response = await adminApi.post('customer?_response=detail', {
        data: {
            id: uuid,
            email,
            password,
            salutationId,
            firstName: 'Pay',
            lastName: 'Parts',
            customerNumber: `payparts-${id}`,
            salesChannelId: shop.salesChannelId,
            groupId: shop.customerGroupId,
            languageId: shop.languageId,
            defaultPaymentMethodId: shop.paymentMethodId,
            defaultBillingAddress: billingAddress,
            defaultShippingAddress: billingAddress,
        },
    });
    const created = await readJson<EntityResponse<{ id: string }>>(response, 'customer');

    return {
        id: created.data.id,
        email,
        password,
    };
}

async function createProduct(
    adminApi: AdminApi,
    idProvider: IdProvider,
    shop: StorefrontShop,
): Promise<string> {
    const { id, uuid } = idProvider.getIdPair();
    const taxResponse = await adminApi.post('search/tax', { data: { limit: 1 } });
    const taxes = await readJson<SearchResponse<{ id: string }>>(taxResponse, 'tax');
    const taxId = taxes.data[0]?.id;

    if (taxId === undefined) {
        throw new Error('No tax rate is available for the checkout product.');
    }

    const price = {
        currencyId: shop.currencyId,
        gross: 10,
        net: 8.4,
        linked: false,
    };
    const response = await adminApi.post('product?_response=detail', {
        data: {
            id: uuid,
            name: `PayParts card ${id}`,
            productNumber: `PAYPARTS-${id}`,
            stock: 10,
            active: true,
            taxId,
            price: [price],
            purchasePrices: [price],
            categories: [{ id: shop.navigationCategoryId }],
            visibilities: [{
                salesChannelId: shop.salesChannelId,
                visibility: 30,
            }],
        },
    });
    const created = await readJson<EntityResponse<{ id: string }>>(response, 'product');
    await clearDelayedCache(adminApi);

    return created.data.id;
}

async function firstCountryStateId(adminApi: AdminApi, countryId: string): Promise<string> {
    const response = await adminApi.post('search/country-state', {
        data: {
            limit: 1,
            filter: [{ type: 'equals', field: 'countryId', value: countryId }],
        },
    });
    const states = await readJson<SearchResponse<{ id: string }>>(response, 'country state');
    const stateId = states.data[0]?.id;

    if (stateId === undefined) {
        throw new Error(`Country ${countryId} requires a state, but none exist.`);
    }

    return stateId;
}

async function readJson<T>(response: { ok(): boolean; text(): Promise<string> }, label: string): Promise<T> {
    const body = await response.text();

    if (!response.ok()) {
        throw new Error(`Failed to load ${label}: ${body}`);
    }

    return JSON.parse(body) as T;
}

function normalizeBaseUrl(url: string): string {
    return `${url.replace(/\/+$/, '')}/`;
}

function urlPath(url: string): string {
    try {
        const path = new URL(url).pathname.replace(/\/+$/, '');

        return path === '' ? '/' : path;
    } catch {
        return '';
    }
}

function requiredEnv(name: string): string {
    const value = process.env[name];

    if (value === undefined || value === '') {
        throw new Error(`Missing ${name}.`);
    }

    return value;
}
