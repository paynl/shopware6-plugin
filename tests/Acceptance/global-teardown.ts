import { execSync } from 'node:child_process';
import { resolve } from 'node:path';

export default function globalTeardown(): void {
    const acceptanceDir = import.meta.dirname;
    execSync('bash scripts/e2e-env.sh redact', {
        cwd: resolve(acceptanceDir),
        stdio: 'inherit',
        env: process.env,
    });
}
