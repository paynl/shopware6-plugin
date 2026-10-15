import { test } from '@shopware-ag/acceptance-test-suite';
import type { Page } from '@playwright/test';
import { payConfigInputMask } from './pay-config-page';

export async function step<T>(page: Page, title: string, body: () => Promise<T>): Promise<T> {
    return test.step(title, async () => {
        try {
            return await body();
        } catch (error) {
            const image = await page.screenshot({
                fullPage: true,
                timeout: 5_000,
                mask: [payConfigInputMask(page)],
            }).catch(() => null);
            if (image) {
                await test.info().attach(title, { body: image, contentType: 'image/png' });
            }
            throw error;
        }
    });
}
