import { expect, type FixtureTypes } from '@shopware-ag/acceptance-test-suite';
import type { Page } from '@playwright/test';
import { parseAmount } from './pay-sandbox';
import { ensureStorefrontDomainAliases, type PayPaymentMethod } from './shopware-admin';
import { step } from './step';
import { addProductToCart, enableGuestCheckout, isPasswordRequired, prepareStorefront } from './storefront';

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
        await prepareStorefront(StorefrontPage);
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

export async function selectPayPaymentMethod(page: Page, payMethod: Pick<PayPaymentMethod, 'id' | 'name'>): Promise<void> {
    const payRadio = page.locator(`input[name="paymentMethodId"][value="${payMethod.id}"]`);
    if (await payRadio.count()) {
        if (!(await payRadio.isChecked())) {
            await payRadio.check({ force: true });
            await page.waitForLoadState('domcontentloaded');
        }
        return;
    }

    await page.getByText(payMethod.name, { exact: true }).first().click();
}

export async function acceptTermsAndSubmitOrder(confirm: CheckoutConfirm): Promise<ParsedAmount> {
    const tos = confirm.termsAndConditionsWithLegalGuaranteeRightsCheckbox
        .or(confirm.termsAndConditionsCheckbox);
    if (await tos.isVisible().catch(() => false)) {
        await tos.check();
    }

    const totalText = (await confirm.grandTotalPrice.textContent()) ?? '€ 10.00';
    const parsed = parseAmount(totalText);
    expect(parsed.amount, `Could not parse checkout total from "${totalText}"`).toBeGreaterThan(0);

    await confirm.submitOrderButton.click();
    return parsed;
}
