"""Fetch a pinned TinyMCE test fixture into ignored tests/results, never runtime."""
from pathlib import Path
import hashlib
import io
import tarfile
import urllib.request

data = urllib.request.urlopen('https://registry.npmjs.org/tinymce/-/tinymce-7.9.2.tgz', timeout=30).read()
assert hashlib.sha256(data).hexdigest() == 'b6a6fa3f6adcb68697b1937769fd1e77ca5a406c02099c60bc00be9699d75097'
dest = (Path(__file__).resolve().parents[1] / 'tests/results/tinymce').resolve()
dest.mkdir(parents=True, exist_ok=True)
with tarfile.open(fileobj=io.BytesIO(data)) as archive:
    for member in archive.getmembers():
        relative = Path(member.name).relative_to('package')
        target = (dest / relative).resolve()
        assert target.is_relative_to(dest)
        if member.isfile():
            target.parent.mkdir(parents=True, exist_ok=True)
            target.write_bytes(archive.extractfile(member).read())
print('TinyMCE 7.9.2 browser fixture ready (pinned SHA-256; excluded from releases)')
