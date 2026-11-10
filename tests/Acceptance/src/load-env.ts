import { existsSync, readFileSync } from 'node:fs';

export function loadEnvFile(path: string): void {
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
        // Command-line env wins, including APP_URL. .env only fills keys that are unset.
        if (process.env[key] === undefined && value !== '') {
            process.env[key] = value;
        }
    }
}
