"""Offline safety regressions. Never connects to a running app or provider."""
import os
from pathlib import Path
import subprocess
import tempfile
import unittest

OPS = Path(__file__).resolve().parents[2] / 'ops'

class SafetyTests(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.root = Path(self.tmp.name)
        (self.root / 'artisan').touch()
        self.bin = self.root / 'bin'
        self.bin.mkdir()
        self.env = dict(os.environ, APP_DIR=str(self.root), APP_ENV_FILE='/dev/null',
                        OPS_ENV_FILE='/dev/null', DEPLOY_HEALTH_URL='https://test.invalid/up', RUN_NPM_BUILD='0', RUN_MIGRATIONS='0',
                        PATH=str(self.bin) + ':' + os.environ['PATH'], QA_ROOT=str(self.root))
        self.stub('git', 'case "$1" in rev-parse) echo abc;; esac')
        self.stub('composer', 'touch "$QA_ROOT/composer-ran"')
        self.stub('id', 'echo 1000')
        self.stub('curl', 'exit "${CURL_STATUS:-0}"')
        self.stub('supervisorctl', 'echo RUNNING')
        self.stub('php', '''
case "$*" in
 *about*) test "${FAIL_BOOT:-0}" = 0;;
 *health:check*)
 n=0; test ! -f "$QA_ROOT/calls" || n=$(cat "$QA_ROOT/calls")
 n=$((n+1)); echo "$n" > "$QA_ROOT/calls"
 test "$n" != "${FAIL_HEALTH_AT:-0}";;
 *tinker*) if test "${FAIL_QUERY:-0}" = 1; then exit 1; else echo 0; fi;;
 *upload-to-spaces*) exit "${UPLOAD_STATUS:-0}";;
esac
''')
    def tearDown(self):
        self.tmp.cleanup()
    def stub(self, name, body):
        p = self.bin / name
        p.write_text('#!/usr/bin/env bash\n' + body + '\n')
        p.chmod(0o755)
    def run_script(self, name, *args, **env):
        return subprocess.run(['bash', str(OPS / name), *args], env=dict(self.env, **env),
                              capture_output=True, text=True)
    def test_healthy_deployment(self):
        r = self.run_script('deploy-release.sh', 'abc')
        self.assertEqual(r.returncode, 0, r.stderr)
        self.assertIn('Deployed commit:', r.stdout)
    def test_preflight_stops_before_install(self):
        r = self.run_script('deploy-release.sh', 'abc', FAIL_HEALTH_AT='1')
        self.assertNotEqual(r.returncode, 0)
        self.assertFalse((self.root / 'composer-ran').exists())
        self.assertNotIn('Deployed commit:', r.stdout)
    def test_post_health_stops_success(self):
        r = self.run_script('deploy-release.sh', 'abc', FAIL_HEALTH_AT='2')
        self.assertNotEqual(r.returncode, 0)
        self.assertNotIn('Deployed commit:', r.stdout)
    def test_boot_failure(self):
        self.assertNotEqual(self.run_script('deploy-release.sh', 'abc', FAIL_BOOT='1').returncode, 0)
    def test_http_failure(self):
        r = self.run_script('deploy-release.sh', 'abc', DEPLOY_HEALTH_URL='https://test.invalid/up', CURL_STATUS='22')
        self.assertNotEqual(r.returncode, 0)
        self.assertNotIn('Deployed commit:', r.stdout)
    def test_alert_failure(self):
        r = self.run_script('alert.sh', 'ERROR', 'Synthetic', OPS_ALERT_WEBHOOK_URL='https://test.invalid', CURL_STATUS='22')
        self.assertNotEqual(r.returncode, 0)
    def test_alert_missing(self):
        self.assertNotEqual(self.run_script('alert.sh', 'INFO', 'Synthetic', OPS_ALERT_WEBHOOK_URL='').returncode, 0)
    def test_alert_success(self):
        self.assertEqual(self.run_script('alert.sh', 'INFO', 'Synthetic', OPS_ALERT_WEBHOOK_URL='https://test.invalid').returncode, 0)
    def test_health_query_failure(self):
        r = self.run_script('health-check.sh', FAIL_QUERY='1', APP_URL='https://test.invalid', OPS_ALERT_WEBHOOK_URL='https://test.invalid')
        self.assertNotEqual(r.returncode, 0)
        self.assertIn('Queue status query failed', r.stdout)
    def test_database_failure_alert(self):
        self.stub('mysqldump', 'exit 2')
        r = self.run_script('backup-db.sh', DB_CONNECTION='mysql', DB_DATABASE='fixture', DB_HOST='invalid', DB_USERNAME='fixture', OPS_ALERT_WEBHOOK_URL='https://test.invalid')
        self.assertNotEqual(r.returncode, 0)
        self.assertIn('backup failed', r.stdout)
        self.assertNotIn('backup completed', r.stdout)
    def test_wrong_storage_disk_rejected(self):
        r = self.run_script('backup-storage.sh', ASSET_DISK='assets', OPS_ALERT_WEBHOOK_URL='https://test.invalid')
        self.assertNotEqual(r.returncode, 0)
    def test_worker_failure(self):
        self.stub('supervisorctl', 'echo STOPPED')
        r = self.run_script('health-check.sh', APP_URL='https://test.invalid', OPS_ALERT_WEBHOOK_URL='https://test.invalid')
        self.assertNotEqual(r.returncode, 0)
        self.assertIn('not running', r.stdout)
    def test_explicit_environment_required(self):
        self.env.pop('OPS_ENV_FILE')
        r = self.run_script('alert.sh', 'INFO', 'Synthetic')
        self.assertNotEqual(r.returncode, 0)
        self.assertIn('OPS_ENV_FILE is required', r.stderr)
    def test_storage_upload_failure(self):
        target = self.root / 'artwork'; target.mkdir(); (target/'logo.png').write_bytes(b'example')
        r = self.run_script('backup-storage.sh', OPS_STORAGE_TARGET_DIR=str(target), OPS_BACKUP_SPACES_BUCKET='test', OPS_BACKUP_SPACES_ENDPOINT='https://test.invalid', UPLOAD_STATUS='1', OPS_ALERT_WEBHOOK_URL='https://test.invalid')
        self.assertNotEqual(r.returncode, 0)
        self.assertIn('backup failed', r.stdout)
        self.assertNotIn('backup completed', r.stdout)

if __name__ == '__main__':
    unittest.main()
