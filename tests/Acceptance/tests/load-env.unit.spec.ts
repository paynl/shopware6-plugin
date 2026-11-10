import { mkdtempSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { expect, test } from '@playwright/test';
import { loadEnvFile } from '../src/load-env';

const KEYS = ['APP_URL', 'ADMIN_API_URL', 'SHOPWARE_ADMIN_PASSWORD', 'PAY_TOKEN_CODE', 'UNSET_FROM_FILE'] as const;

test('explicit environment wins and .env only fills keys that are unset', () => {
    const dir = mkdtempSync(join(tmpdir(), 'paynl-env-'));
    const path = join(dir, '.env');
    writeFileSync(path, [
        'APP_URL=http://from-file/',
        'ADMIN_API_URL=http://from-file/',
        'SHOPWARE_ADMIN_PASSWORD=from-file',
        'PAY_TOKEN_CODE=from-file',
        'UNSET_FROM_FILE=from-file',
        'EMPTY_VALUE=',
    ].join('\n'));

    const previous = new Map(KEYS.map((key) => [key, process.env[key]]));
    const emptyPrevious = process.env.EMPTY_VALUE;
    process.env.APP_URL = 'http://from-process/';
    process.env.ADMIN_API_URL = 'http://from-process/';
    process.env.SHOPWARE_ADMIN_PASSWORD = 'from-process';
    process.env.PAY_TOKEN_CODE = 'from-process';
    delete process.env.UNSET_FROM_FILE;
    delete process.env.EMPTY_VALUE;

    try {
        loadEnvFile(path);
        expect(process.env.APP_URL).toBe('http://from-process/');
        expect(process.env.ADMIN_API_URL).toBe('http://from-process/');
        expect(process.env.SHOPWARE_ADMIN_PASSWORD).toBe('from-process');
        expect(process.env.PAY_TOKEN_CODE).toBe('from-process');
        expect(process.env.UNSET_FROM_FILE).toBe('from-file');
        expect(process.env.EMPTY_VALUE).toBeUndefined();
    } finally {
        for (const [key, value] of previous) {
            if (value === undefined) {
                delete process.env[key];
            } else {
                process.env[key] = value;
            }
        }
        if (emptyPrevious === undefined) {
            delete process.env.EMPTY_VALUE;
        } else {
            process.env.EMPTY_VALUE = emptyPrevious;
        }
        rmSync(dir, { recursive: true });
    }
});
