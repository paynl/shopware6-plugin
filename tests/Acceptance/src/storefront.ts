import { expect, type BrowserContext, type Page } from '@playwright/test';

const PROFILER_CSS = '.sf-toolbar, .sf-toolbar-block { display: none !important; pointer-events: none !important; }';
const ACCEPT_ALL_COOKIES = /accept all|alle cookies accepteren|alle cookies akzeptieren/i;

export async function prepareStorefront(page: Page): Promise<void> {
    // Only the shop needs this. A page-wide header is copied onto the iDEAL
    // simulator call and that API rejects it in the CORS preflight.
    const appUrl = (process.env.APP_URL ?? '').replace(/\/$/, '');
    if (appUrl) {
        await page.route('**/*', async (route) => {
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
    }
    if (await page.getByText(/you are about to visit|ngrok-free\.app|visit site/i).first().isVisible({ timeout: 2000 }).catch(() => false)) {
        await page.reload({ waitUntil: 'domcontentloaded' });
    }
    await hideProfiler(page);
    await acceptCookies(page);
}

export async function acceptCookies(page: Page): Promise<void> {
    const acceptAll = page.getByRole('button', { name: ACCEPT_ALL_COOKIES }).first();
    if (await acceptAll.isVisible({ timeout: 4000 }).catch(() => false)) {
        await acceptAll.click();
        return;
    }

    const requiredOnly = page.getByRole('button', { name: /only technically required/i }).first();
    if (await requiredOnly.isVisible({ timeout: 1000 }).catch(() => false)) {
        await requiredOnly.click();
    }
}

export async function enableGuestCheckout(page: Page): Promise<boolean> {
    const createAccount = page.getByRole('checkbox', { name: /create a customer account/i });
    if (await createAccount.isVisible({ timeout: 3000 }).catch(() => false)) {
        if (await createAccount.isChecked()) {
            await createAccount.uncheck({ force: true });
        }
        await page.locator('#personalPassword, input[name="password"]').first()
            .waitFor({ state: 'hidden', timeout: 5000 })
            .catch(() => undefined);
        return true;
    }

    const guest = page.locator('#personalGuest, input[name="guest"]').first();
    if (await guest.isVisible({ timeout: 1000 }).catch(() => false)) {
        await guest.check({ force: true });
        return true;
    }

    return false;
}

export async function isPasswordRequired(page: Page): Promise<boolean> {
    const password = page.locator('#personalPassword, input[name="password"]').first();
    return password.isVisible({ timeout: 1000 }).catch(() => false);
}

export async function hideProfiler(page: Page): Promise<void> {
    await page.addStyleTag({ content: PROFILER_CSS }).catch(() => undefined);
}

export async function hideProfilerOnContext(context: BrowserContext): Promise<void> {
    await context.addInitScript((css: string) => {
        const style = document.createElement('style');
        style.textContent = css;
        document.documentElement.append(style);
    }, PROFILER_CSS);
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
    if (await quantityField.isVisible().catch(() => false)) {
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
