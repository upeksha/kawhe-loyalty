"""Offline recovery integrity gates; no provider access."""
import hashlib
import json
from pathlib import Path
import subprocess
import sys
import tempfile
import unittest

VERIFY = Path(__file__).resolve().parents[2] / 'ops/verify-artwork.py'


class ArtworkIntegrityTests(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.root = Path(self.tmp.name)
        (self.root / 'files').mkdir()
        (self.root / 'files/logo.png').write_bytes(b'fixture image')
        self.manifest = {'version': 1, 'files': [{'key': 'logo.png', 'bytes': 13,
                         'sha256': hashlib.sha256(b'fixture image').hexdigest()}]}

    def tearDown(self):
        self.tmp.cleanup()

    def verify(self):
        (self.root / 'manifest.json').write_text(json.dumps(self.manifest))
        return subprocess.run([sys.executable, str(VERIFY), str(self.root)], capture_output=True).returncode

    def test_intact(self):
        self.assertEqual(self.verify(), 0)

    def test_changed_bytes(self):
        (self.root / 'files/logo.png').write_bytes(b'altered image')
        self.assertNotEqual(self.verify(), 0)

    def test_missing_file(self):
        (self.root / 'files/logo.png').unlink()
        self.assertNotEqual(self.verify(), 0)

    def test_extra_file(self):
        (self.root / 'files/extra').touch()
        self.assertNotEqual(self.verify(), 0)

    def test_traversal(self):
        self.manifest['files'][0]['key'] = '../../outside'
        self.assertNotEqual(self.verify(), 0)

    def test_symlink(self):
        (self.root / 'files/logo.png').unlink()
        (self.root / 'outside').write_bytes(b'fixture image')
        (self.root / 'files/logo.png').symlink_to(self.root / 'outside')
        self.assertNotEqual(self.verify(), 0)


if __name__ == '__main__':
    unittest.main()
