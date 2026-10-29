import { expect, type Page } from '@playwright/test';

type CookieGroups = {
    hash?: string;
    languageId?: string;
};

const COOKIE_MAX_AGE_SECONDS = 30 * 24 * 60 * 60;

function storefrontOrigin(page: Page): URL | undefined {
    const current = page.url();
    const candidate = current.startsWith('http://') || current.startsWith('https://')
        ? current
        : process.env.APP_URL;
    if (candidate === undefined || candidate === '') {
        return undefined;
    }

    return new URL(candidate);
}

/** Sets Shopware's consent cookies. 6.7 shows the banner again when the language hash is missing. */
export async function acceptCookies(page: Page): Promise<void> {
    const origin = storefrontOrigin(page);
    if (origin === undefined) {
        return;
    }

    const expires = Math.floor(Date.now() / 1000) + COOKIE_MAX_AGE_SECONDS;
    const cookies = [{
        name: 'cookie-preference',
        value: '1',
        url: `${origin.origin}/`,
        expires,
    }];

    const groups = await page.request.get(new URL('/cookie/groups', origin).href, {
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'ngrok-skip-browser-warning': 'true',
        },
        failOnStatusCode: false,
    }).catch(() => null);
    if (groups?.ok()) {
        const body = await groups.json().catch(() => null) as CookieGroups | null;
        if (body?.hash && body.languageId) {
            cookies.push({
                name: 'cookie-config-hash',
                value: JSON.stringify({ [body.languageId]: body.hash }),
                url: `${origin.origin}/`,
                expires,
            });
        }
    }

    await page.context().addCookies(cookies);
}

/** The register form is server-rendered, so once its e-mail field shows, `isVisible()` on the rest is final. */
async function waitForRegisterForm(page: Page): Promise<void> {
    await expect(
        page.locator('#personalMail, input[name="email"]').first(),
        'Checkout register form was not shown',
    ).toBeVisible();
}

export async function enableGuestCheckout(page: Page): Promise<boolean> {
    await waitForRegisterForm(page);

    const createAccount = page.getByRole('checkbox', { name: /create a customer account/i });
    if (await createAccount.isVisible()) {
        if (await createAccount.isChecked()) {
            await createAccount.uncheck({ force: true });
        }
        await page.locator('#personalPassword, input[name="password"]').first()
            .waitFor({ state: 'hidden', timeout: 5000 })
            .catch(() => undefined);
        return true;
    }

    const guest = page.locator('#personalGuest, input[name="guest"]').first();
    if (await guest.isVisible()) {
        await guest.check({ force: true });
        return true;
    }

    return false;
}

export async function isPasswordRequired(page: Page): Promise<boolean> {
    const password = page.locator('#personalPassword, input[name="password"]').first();
    await waitForRegisterForm(page);
    return password.isVisible();
}

async function storefrontFailureContext(page: Page): Promise<string> {
    const title = await page.title().catch(() => '');
    const heading = await page.locator('h1, .alert, .product-detail-name').first().textContent().catch(() => '');
    return `url=${page.url()} title=${title.trim()} heading=${(heading || '').trim().slice(0, 180)}`;
}

export async function addProductToCart(page: Page, quantity = '1'): Promise<void> {
    const context = await storefrontFailureContext(page);
    if (/unknown Domain|Sales Channel Domains|Domain Mapping/i.test(context)) {
        throw new Error(`Shopware does not recognise this storefront URL. ${context}`);
    }
    if (/verify you are human|just a moment|attention required|you are about to visit/i.test(context)) {
        throw new Error(`Tunnel or bot check blocked the storefront. ${context}`);
    }

    const addToCart = page.locator('button.btn-buy').or(
        page.getByRole('button', { name: /add to shopping cart|add to cart|in den warenkorb|in winkelwagen/i }),
    ).first();
    await expect(
        addToCart,
        `Add to cart was not shown. ${context}`,
    ).toBeVisible({ timeout: 20_000 });

    const quantityField = page.locator('.buy-widget:not(.d-none), .product-detail-buy').locator(
        'select#quantity, input[name="quantity"], input.quantity-selector-group-input, .product-detail-quantity-select',
    ).first();
    // Rendered with the buy button, which is already visible.
    if (await quantityField.isVisible()) {
        const tag = await quantityField.evaluate((element) => element.tagName);
        if (tag === 'SELECT') {
            await quantityField.selectOption(quantity);
        } else {
            await quantityField.fill(quantity);
        }
    }

    await addToCart.click();
    await expect(page.getByRole('dialog').or(page.locator('.offcanvas'))).toBeVisible({ timeout: 15_000 });
}
