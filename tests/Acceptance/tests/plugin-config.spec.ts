import { expect, test } from '@shopware-ag/acceptance-test-suite';
import type { Page, Response } from '@playwright/test';
import { dismissAdminPopups } from '../src/admin-popups';
import { maskPayConfigCredentialInputs, payConfigPageTestUse } from '../src/pay-config-page';
import { step } from '../src/step';

const SUCCESS_TEXT = /correct credentials|link erfolgreich|koppeling geslaagd|paynlValidation\.messages\.correctCredentials/i;

function expectPayCredentialsEnv(): void {
    expect(
        process.env.PAY_TOKEN_CODE && process.env.PAY_API_TOKEN && process.env.PAY_SERVICE_ID,
        'PAY_TOKEN_CODE, PAY_API_TOKEN and PAY_SERVICE_ID must be set',
    ).toBeTruthy();
}

function configInput(page: Page, name: string, label: string) {
    return page.locator(`input[name="${name}"], input[aria-label*="${label}"]`).first();
}

async function expectPrefilledConfigInput(page: Page, name: string, label: string, fieldLabel: string): Promise<void> {
    const input = configInput(page, name, label);
    await expect(input).toBeVisible();
    const length = (await input.inputValue()).length;
    expect(length > 0, `${fieldLabel} must be pre-configured (e2e configure or local setup)`).toBe(true);
}

test.use(payConfigPageTestUse);

test.describe('PAY. plugin config', () => {
    test('Test API Keys connects with the sales location', async ({ AdminPage }) => {
        expectPayCredentialsEnv();

        await step(AdminPage, 'Open the PAY. configuration screen', async () => {
            await dismissAdminPopups(AdminPage);
            await AdminPage.goto('./#/sw/extension/config/PaynlPaymentShopware6');
            await expect(AdminPage).toHaveURL(/extension\/config\/PaynlPaymentShopware6/);
            await dismissAdminPopups(AdminPage);
            await expect(configInput(AdminPage, 'PaynlPaymentShopware6.config.tokenCode', 'Token-Code')).toBeVisible({
                timeout: 60_000,
            });
            await maskPayConfigCredentialInputs(AdminPage);
        });

        await step(AdminPage, 'Confirm Token-Code, API-token and Service-ID are pre-configured', async () => {
            await expectPrefilledConfigInput(
                AdminPage,
                'PaynlPaymentShopware6.config.tokenCode',
                'Token-Code',
                'Token-Code',
            );
            await expectPrefilledConfigInput(
                AdminPage,
                'PaynlPaymentShopware6.config.apiToken',
                'API-token',
                'API-token',
            );
            await expectPrefilledConfigInput(
                AdminPage,
                'PaynlPaymentShopware6.config.serviceId',
                'Service-ID',
                'Service-ID',
            );
        });

        const connection = await step(AdminPage, 'Click Test API Keys', async () => {
            await dismissAdminPopups(AdminPage);
            const responsePromise = AdminPage.waitForResponse(
                (response) => response.url().includes('/paynl/test-api-keys') && response.request().method() === 'POST',
                { timeout: 60_000 },
            );
            await AdminPage.getByRole('button', { name: /test api keys|api-schlüssel testen|test api sleutel/i }).click();
            return responsePromise;
        });

        await step(AdminPage, 'Confirm the connection succeeded', async () => {
            const payload = await readConnectionResponse(connection);
            expect(payload.success, payload.message || 'Test API Keys did not return a successful response').toBe(true);
            expect(payload.message).toBe('paynlValidation.messages.correctCredentials');

            const notifications = AdminPage.locator('.sw-notifications');
            await expect(notifications).toBeVisible();
            await expect(notifications).toContainText(SUCCESS_TEXT);
        });
    });
});

async function readConnectionResponse(response: Response): Promise<{ success?: boolean; message?: string }> {
    const body = await response.text();
    expect(response.ok(), `Test API Keys failed: ${response.status()} ${body}`).toBeTruthy();
    return JSON.parse(body) as { success?: boolean; message?: string };
}
