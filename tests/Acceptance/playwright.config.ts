import { existsSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { defineConfig, devices } from '@playwright/test';

function loadEnvFile(path: string): void {
    if (!existsSync(path)) {
        return;
    }

    for (const line of readFileSync(path, 'utf8').split('\n')) {
        const trimmed = line.trim();
        if (!trimmed || trimmed.startsWith('#')) {
            continue;
        }

        const separator = trimmed.indexOf('=');
        if (separator === -1) {
            continue;
        }

        const key = trimmed.slice(0, separator);
        const value = trimmed.slice(separator + 1).replace(/^['"]|['"]$/g, '');
        // The tunnel URLs in .env win. A leftover 127.0.0.1 in the process environment
        // makes Shopware return administration assets the browser never requests.
        if (
            process.env[key] === undefined
            || key === 'APP_URL'
            || key === 'ADMIN_API_URL'
            || key === 'SHOPWARE_ADMIN_PASSWORD'
        ) {
            if (value !== '') {
                process.env[key] = value;
            }
        }
    }
}

loadEnvFile(resolve(import.meta.dirname, '.env'));

const isCi = !!process.env.CI;

export default defineConfig({
    testDir: './tests',
    globalSetup: './global-setup.ts',
    globalTeardown: './global-teardown.ts',
    fullyParallel: false,
    forbidOnly: isCi,
    retries: isCi ? 1 : 0,
    workers: 1,
    timeout: 180_000,
    expect: {
        timeout: 15_000,
    },
    reporter: isCi
        ? [
            ['list'],
            ['github'],
            ['html', { open: 'never', outputFolder: 'playwright-report' }],
            ['junit', { outputFile: 'test-results/junit.xml' }],
        ]
        : [
            ['list'],
            ['html', { open: 'never', outputFolder: 'playwright-report' }],
        ],
    use: {
        baseURL: process.env.APP_URL,
        ignoreHTTPSErrors: true,
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
        actionTimeout: 20_000,
        navigationTimeout: 60_000,
    },
    outputDir: 'test-results',
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
    ],
});
