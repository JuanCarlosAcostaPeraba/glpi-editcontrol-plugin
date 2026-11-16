"""Build a reproducible installable archive with the mandatory editcontrol root."""
from pathlib import Path
import hashlib
import zipfile

root = Path(__file__).resolve().parents[1]
dest = root / 'dist'
dest.mkdir(exist_ok=True)
archive = dest / 'editcontrol-0.1.0.zip'
excluded = {'.git', '.codebase-memory', 'dist', 'vendor', '__pycache__'}
with zipfile.ZipFile(archive, 'w', compression=zipfile.ZIP_DEFLATED) as z:
    for file in sorted(root.rglob('*')):
        relative = file.relative_to(root)
        if not file.is_file() or any(p in excluded for p in relative.parts):
            continue
        if relative.parts[:2] == ('tests', 'results') or file.name == '.env':
            continue
        info = zipfile.ZipInfo('editcontrol/' + relative.as_posix(), (2026, 11, 16, 0, 0, 0))
        info.compress_type = zipfile.ZIP_DEFLATED
        info.external_attr = 0o100644 << 16
        z.writestr(info, file.read_bytes())
digest = hashlib.sha256(archive.read_bytes()).hexdigest()
(dest / (archive.name + '.sha256')).write_text(digest + '  ' + archive.name + '\n', encoding='ascii')
print(archive)
print(digest)
