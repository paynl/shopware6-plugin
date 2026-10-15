import type { Locator } from '@playwright/test';

export const appears = (locator: Locator, timeout: number): Promise<boolean> =>
    locator.waitFor({ state: 'visible', timeout }).then(() => true, () => false);
