import { expect, type Page } from '@playwright/test';

export async function dismissAdminPopups(page: Page): Promise<void> {
    for (let attempt = 0; attempt < 5; attempt += 1) {
        const closedUpdate = await dismissShopwareUpdate(page);
        const closedConsent = await dismissUsageConsent(page);
        if (!closedUpdate && !closedConsent) {
            break;
        }
    }

    await removeStrayModalBackdrop(page);
}

async function dismissShopwareUpdate(page: Page): Promise<boolean> {
    const banner = page.getByRole('banner').filter({
        hasText: /a new shopware version|eine neue shopware-version|een nieuwe shopware-versie/i,
    });

    if (!await banner.first().isVisible().catch(() => false)) {
        return false;
    }

    const close = banner.getByRole('button', { name: /^(close|schließen|sluiten)$/i });
    if (await close.first().isVisible().catch(() => false)) {
        await close.first().click({ force: true });
    } else {
        await banner.getByRole('button', { name: /^(cancel|abbrechen|annuleren)$/i }).first().click({ force: true });
    }

    await expect(banner.first()).toBeHidden({ timeout: 15_000 });
    return true;
}

async function dismissUsageConsent(page: Page): Promise<boolean> {
    const consentModal = page.getByRole('dialog').filter({
        hasText: /help us to improve shopware|hilf uns dabei, shopware zu verbessern|help ons shopware te verbeteren/i,
    }).last();

    if (!await consentModal.isVisible().catch(() => false)) {
        return false;
    }

    await consentModal.getByRole('button', {
        name: /^(reject all|alle ablehnen|alles afwijzen)$/i,
    }).click({ force: true });
    await expect(consentModal).toBeHidden();
    return true;
}

async function removeStrayModalBackdrop(page: Page): Promise<void> {
    await page.evaluate(() => {
        if (document.querySelector('[role="dialog"]')) {
            return;
        }
        document.querySelectorAll('[data-testid="modal-backdrop"], .mt-modal-root__backdrop, .sw-modal-backdrop, .sw-modal__backdrop')
            .forEach((backdrop) => backdrop.remove());
    }).catch(() => undefined);
}
