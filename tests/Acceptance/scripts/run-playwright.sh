#!/usr/bin/env bash
# Playwright's HTML reporter writes after global teardown, so redaction has to run
# once this process exits. npm posttest does not run when the test script fails.
set -u

status=0
playwright test "$@" || status=$?

bash scripts/e2e-env.sh redact || exit $?
exit "${status}"
