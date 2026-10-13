# Testing Wallet and email worker isolation

Testing previously consumed `default,emails` in one process. Wallet jobs on
`default` could starve email, and a job already running cannot be preempted by
changing queue priority. Run the two dedicated workers in
`ops/supervisor/kawhe-testing-workers.conf` instead. Existing queue names and
Wallet retry/idempotency behavior are unchanged.

This template is for `/var/www/kawhe-testing` only. Replace the existing testing
queue program definition, preserving its name, and add the email program. Do
not duplicate a program across Supervisor files. Use targeted Supervisor
`update kawhe-testing-queue kawhe-testing-emails`; never restart Supervisor or
live programs. Add `kawhe-testing-emails` to testing's `OPS_SUPERVISOR_SERVICES`
so the existing health check alerts when that worker stops.

Set testing `MAIL_WELCOME_SYNC=false` now that email has its own worker, and
`MAIL_TIMEOUT=15`. Rebuild testing's config cache before starting the workers.
Both worker timeouts must remain below database `retry_after` (currently 90
seconds). The PHP memory limit is 96 MB per worker with graceful recycling at
80 MB or one hour. This bounds individual workers; it is not a host-wide
resource or throughput guarantee.

For this config-only release, verify composer.json/composer.lock are unchanged
before reusing installed vendor dependencies. No migration or asset build is
needed. Stop only the testing queue during the checkout/config-cache change;
check application/HTTP health before and afterwards. Preserve live checksums
and process IDs. Keep a backup of the prior testing configuration for rollback.

Validation:

```
APP_ENV=testing php tests/Ops/queue-isolation.php
APP_ENV=testing php vendor/bin/pest --compact
python3 tests/Ops/test_safety.py
```

The queue probe creates a disposable SQLite database and launches the exact
worker commands from the template locally. `--hosted` instead uses existing
workers and requires the exact testing checkout and URL. Run hosted as
`www-data` so both workers can access its private temporary marker directory.
It exercises slow work, released/retried work and a terminal failure on
`default`, while rendering a real recovery email through the independent
`emails` worker. Probe messages use the **log** mailer and an invalid example
address; they never send to a real customer. It deletes only its own queue and
failure records. This proves worker isolation and rendering, not inbox delivery
or physical Wallet refresh. Real SMTP acceptance and inbox delivery must be
reported separately.

For an explicitly authorized real recipient, hosted mode accepts
`QUEUE_PROBE_RECIPIENT`. It sends one clearly labelled plain-text probe through
the configured mailer during the slow-job scenario, in addition to the log
checks. No customer verification/reset tokens or records are created.
