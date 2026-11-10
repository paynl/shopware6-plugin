import { execSync } from 'node:child_process';
import { resolve } from 'node:path';

export default function globalSetup(): void {
    const acceptanceDir = import.meta.dirname;
    execSync('bash scripts/e2e-env.sh ensure-plugin', {
        cwd: resolve(acceptanceDir),
        stdio: 'inherit',
        env: process.env,
    });
}
