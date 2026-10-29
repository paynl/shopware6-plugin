import { resolve } from 'node:path';
import { defineConfig, devices } from '@playwright/test';
import { loadEnvFile } from './src/load-env';

loadEnvFile(resolve(import.meta.dirname, '.env'));

const isCi = !!process.env.CI;

// ngrok shows an interstitial to any user agent that contains "Mozilla".
// A non-browser agent skips it, so the suite does not intercept every request.
function ngrokUserAgent(appUrl: string | undefined): string | undefined {
    if (appUrl === undefined || appUrl === '') {
        return undefined;
    }

    try {
        const host = new URL(appUrl).hostname;
        if (/(?:^|\.)ngrok(?:-free)?\.(?:app|dev|io)$/i.test(host)) {
            return 'paynl-shopware6-acceptance';
        }
    } catch {
        return undefined;
    }

    return undefined;
}

const userAgent = ngrokUserAgent(process.env.APP_URL);

export default defineConfig({
    testDir: './tests',
    // CI runs `e2e-env.sh up` first, which already calls configure_shopware.
    // Local `npm test` still installs the plugin through global-setup.
    globalSetup: isCi ? undefined : './global-setup.ts',
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
        // Keep the failed first attempt when the retry passes, and keep that retry too.
        trace: 'retain-on-failure-and-retries',
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
        actionTimeout: 20_000,
        navigationTimeout: 60_000,
    },
    outputDir: 'test-results',
    projects: [
        {
            name: 'chromium',
            // The device preset sets a Mozilla user agent, so the ngrok override must come after it.
            use: { ...devices['Desktop Chrome'], ...(userAgent === undefined ? {} : { userAgent }) },
        },
    ],
});
