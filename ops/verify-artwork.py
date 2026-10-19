#!/usr/bin/env python3
"""Verify an extracted trusted snapshot. Never modifies files or remote storage."""
import hashlib
import json
from pathlib import Path
import sys

root = Path(sys.argv[1]).resolve()
manifest = json.loads((root / 'manifest.json').read_text())
assert manifest['version'] == 1 and manifest['files'], 'Invalid/empty manifest'
expected = set()
for entry in manifest['files']:
    key = entry['key']
    path = root / 'files' / key
    assert not path.is_symlink() and path.resolve().is_relative_to(root / 'files'), 'Unsafe path'
    assert key not in expected, 'Duplicate entry'
    expected.add(key)
    assert path.stat().st_size == entry['bytes'], f'Size mismatch: {key}'
    with path.open('rb') as stream:
        digest = hashlib.file_digest(stream, 'sha256').hexdigest()
    assert digest == entry['sha256'], f'Hash mismatch: {key}'
actual = {str(p.relative_to(root / 'files')) for p in (root / 'files').rglob('*') if p.is_file()}
assert actual == expected, 'Unexpected or missing files'
print(f'Verified {len(expected)} artwork files')
