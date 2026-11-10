import { expect, type FixtureTypes } from '@shopware-ag/acceptance-test-suite';
import type { Locator, Page, Response } from '@playwright/test';
import { parseAmount } from './pay-sandbox';
import { ensureStorefrontDomainAliases, type PayPaymentMethod } from './shopware-admin';
import { step } from './step';
import { addProductToCart, enableGuestCheckout, isPasswordRequired, acceptCookies } from './storefront';

type RegisterGuest = (options: { isGuest: boolean; password: string }) => () => Promise<void>;

export type GuestCheckout = Pick<
    FixtureTypes,
    | 'StorefrontPage'
    | 'StorefrontProductDetail'
    | 'ProductData'
    | 'DefaultSalesChannel'
    | 'AdminApiContext'
    | 'ShopCustomer'
> & {
    Register: RegisterGuest;
};

type CheckoutConfirm = FixtureTypes['StorefrontCheckoutConfirm'];
type ParsedAmount = ReturnType<typeof parseAmount>;

export async function reachGuestCheckoutConfirm(fixtures: GuestCheckout): Promise<void> {
    const {
        StorefrontPage,
        StorefrontProductDetail,
        ProductData,
        DefaultSalesChannel,
        AdminApiContext,
        ShopCustomer,
        Register,
    } = fixtures;

    await step(StorefrontPage, 'Open the storefront', async () => {
        await ensureStorefrontDomainAliases(
            AdminApiContext,
            DefaultSalesChannel.url,
            DefaultSalesChannel.salesChannel.id,
        );
        await acceptCookies(StorefrontPage);
        await StorefrontPage.goto('./', { waitUntil: 'domcontentloaded' });
    });

    await step(StorefrontPage, 'Add the product and go to checkout', async () => {
        await ShopCustomer.goesTo(`detail/${ProductData.id}`);
        await addProductToCart(StorefrontPage);
        await StorefrontProductDetail.offCanvasCartGoToCheckoutButton.click();
        await StorefrontPage.waitForURL(/checkout\/(register|confirm)/);
    });

    await step(StorefrontPage, 'Register as a guest', async () => {
        if (!StorefrontPage.url().includes('/checkout/register')) {
            return;
        }

        await enableGuestCheckout(StorefrontPage);
        const guest = !(await isPasswordRequired(StorefrontPage));
        await ShopCustomer.attemptsTo(Register({ isGuest: guest, password: 'shopware' }));
        await StorefrontPage.waitForURL(/checkout\/confirm/, { timeout: 30_000 });
    });
}

function isConfirmDocument(response: Response): boolean {
    if (response.request().method() !== 'GET' || response.request().resourceType() !== 'document') {
        return false;
    }

    try {
        return new URL(response.url()).pathname.endsWith('/checkout/confirm');
    } catch {
        return false;
    }
}

/** Shopware posts the payment form and reloads /checkout/confirm. The current document is already loaded. */
export async function waitForConfirmReload(page: Page, action: () => Promise<void>): Promise<void> {
    const reloaded = page.waitForResponse(isConfirmDocument, { timeout: 30_000 });
    void reloaded.catch(() => undefined);
    await action();
    await reloaded;
    await page.waitForLoadState('domcontentloaded');
}

export async function checkPaymentMethod(page: Page, radio: Locator): Promise<void> {
    if (await radio.isChecked()) {
        return;
    }

    const autoSubmits = await radio.evaluate((element) => element.classList.contains('payment-method-input'));
    if (autoSubmits) {
        await waitForConfirmReload(page, () => radio.check({ force: true }));
        return;
    }

    await radio.check({ force: true });
}

export async function selectPayPaymentMethod(page: Page, payMethod: Pick<PayPaymentMethod, 'id' | 'name'>): Promise<void> {
    const payRadio = page.locator(`input[name="paymentMethodId"][value="${payMethod.id}"]`);
    if (await payRadio.count()) {
        await checkPaymentMethod(page, payRadio);
        return;
    }

    const byName = page.getByText(payMethod.name, { exact: true }).first();
    await waitForConfirmReload(page, () => byName.click());
}

export async function acceptTermsAndSubmitOrder(confirm: CheckoutConfirm): Promise<ParsedAmount> {
    const tos = confirm.termsAndConditionsWithLegalGuaranteeRightsCheckbox
        .or(confirm.termsAndConditionsCheckbox)
        .first();
    if (await tos.isVisible()) {
        await tos.check();
    }

    await expect(confirm.grandTotalPrice, 'Checkout grand total was not shown').toBeVisible();
    const totalText = (await confirm.grandTotalPrice.textContent())?.trim() ?? '';
    expect(totalText, 'Checkout grand total was empty').not.toEqual('');
    const parsed = parseAmount(totalText);
    expect(parsed.amount, `Could not parse checkout total from "${totalText}"`).toBeGreaterThan(0);

    await confirm.submitOrderButton.click();
    return parsed;
}
