import { expect, type Browser, type Page } from '@playwright/test';

const ADMIN_POPUP = /help us to improve shopware|hilf uns dabei, shopware zu verbessern|help ons shopware te verbeteren|a new shopware version|eine neue shopware-version|een nieuwe shopware-versie/i;

export async function dismissAdminPopups(page: Page, waitMs = 8_000): Promise<void> {
    await page.getByText(ADMIN_POPUP).first()
        .waitFor({ state: 'visible', timeout: waitMs })
        .catch(() => undefined);

    for (let attempt = 0; attempt < 5; attempt += 1) {
        const closedUpdate = await dismissShopwareUpdate(page);
        const closedConsent = await dismissUsageConsent(page);
        if (!closedUpdate && !closedConsent) {
            break;
        }
    }

    await removeStrayModalBackdrop(page);
}

export async function openAdmin(browser: Browser): Promise<Page> {
    const context = await browser.newContext({
        baseURL: adminBaseUrl(),
        ignoreHTTPSErrors: true,
    });
    const page = await context.newPage();

    const username = process.env.SHOPWARE_ADMIN_USERNAME || 'admin';
    const password = process.env.SHOPWARE_ADMIN_PASSWORD;
    expect(password, 'SHOPWARE_ADMIN_PASSWORD must be set (run env:up or export it)').toBeTruthy();
    await page.goto('./#/login', { waitUntil: 'domcontentloaded' });

    const usernameField = page.getByRole('textbox', { name: /username|email address|benutzername|e-mailadresse|gebruikersnaam/i });
    await expect(usernameField).toBeVisible({ timeout: 60_000 });
    await usernameField.fill(username);
    await page.getByRole('textbox', { name: /^(password|passwort|wachtwoord)$/i }).fill(password!);
    const loginButton = page.getByRole('button', { name: /^(log in|anmelden|inloggen)$/i });
    await expect(loginButton).toBeEnabled();
    await loginButton.click();
    await page.waitForURL((url) => {
        const hash = url.hash.toLowerCase();
        return hash.startsWith('#/') && !hash.includes('login');
    }, { timeout: 60_000 });
    await dismissAdminPopups(page);

    return page;
}

function adminBaseUrl(): string {
    if (process.env.ADMIN_URL) {
        return process.env.ADMIN_URL.endsWith('/') ? process.env.ADMIN_URL : `${process.env.ADMIN_URL}/`;
    }

    const appUrl = process.env.APP_URL || '';
    expect(appUrl, 'APP_URL must be set').toBeTruthy();
    return `${appUrl.replace(/\/$/, '')}/admin/`;
}

async function dismissShopwareUpdate(page: Page): Promise<boolean> {
    const updatePopup = page.locator('div').filter({
        hasText: /a new shopware version|eine neue shopware-version|een nieuwe shopware-versie/i,
        has: page.getByRole('button', { name: /open update|update öffnen|update openen/i }),
    }).last();

    if (!await updatePopup.isVisible().catch(() => false)) {
        return false;
    }

    const cancel = updatePopup.getByRole('button', { name: /^(cancel|abbrechen|annuleren)$/i });
    await cancel.click({ force: true });
    await expect(updatePopup).toBeHidden();
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
