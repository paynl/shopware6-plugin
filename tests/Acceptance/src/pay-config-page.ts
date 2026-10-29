import type { Page } from '@playwright/test';

/** Playwright options for specs that open the PAY. plugin config screen (credentials may be on screen). */
export const payConfigPageTestUse = {
    video: 'off' as const,
    screenshot: 'off' as const,
    trace: { mode: 'retain-on-failure-and-retries' as const, screenshots: false },
};

export function payConfigInputMask(page: Page) {
    return page.locator('input[name^="PaynlPaymentShopware6.config."]');
}

const CREDENTIAL_FIELDS = [
    { name: 'PaynlPaymentShopware6.config.tokenCode', label: 'Token-Code' },
    { name: 'PaynlPaymentShopware6.config.apiToken', label: 'API-token' },
    { name: 'PaynlPaymentShopware6.config.serviceId', label: 'Service-ID' },
] as const;

/** Plain-text config fields show saved tokens; mask them in screenshots and failure artifacts. */
export async function maskPayConfigCredentialInputs(page: Page): Promise<void> {
    for (const field of CREDENTIAL_FIELDS) {
        const input = page.locator(`input[name="${field.name}"], input[aria-label*="${field.label}"]`).first();
        await input.evaluate((element) => {
            if (element instanceof HTMLInputElement) {
                element.type = 'password';
            }
        }).catch(() => undefined);
    }
}
