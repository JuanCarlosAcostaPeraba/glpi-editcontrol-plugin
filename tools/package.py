"""Build reproducible GLPI ZIP and tar.gz with runtime files and public docs."""
from pathlib import Path
import gzip
import hashlib
import io
import re
import tarfile
import zipfile

root = Path(__file__).resolve().parents[1]
dest = root / 'dist'
dest.mkdir(exist_ok=True)
version = re.search(r"define\('PLUGIN_EDITCONTROL_VERSION', '([^']+)'\)", (root / 'setup.php').read_text(encoding='utf-8')).group(1)
directories = {'ajax', 'front', 'inc', 'install', 'public', 'docs'}
names = {'setup.php', 'hook.php', 'LICENSE', 'README.md', 'CHANGELOG.md', 'SECURITY.md', 'SUPPORT.md', 'CONTRIBUTING.md', 'editcontrol.xml', 'editcontrol.png'}
files = [p for p in sorted(root.rglob('*')) if p.is_file() and (p.relative_to(root).parts[0] in directories or p.relative_to(root).as_posix() in names)]
payload = {}
for file in files:
    data = file.read_bytes()
    if file.suffix in {'.php', '.js', '.css', '.sql', '.md', '.txt', '.xml'}:
        data = data.replace(b'\r\n', b'\n')
    payload['editcontrol/' + file.relative_to(root).as_posix()] = data
archive = dest / f'editcontrol-{version}.zip'
with zipfile.ZipFile(archive, 'w', compression=zipfile.ZIP_DEFLATED) as z:
    for name, data in payload.items():
        info = zipfile.ZipInfo(name, (2026, 11, 17, 0, 0, 0))
        info.compress_type = zipfile.ZIP_DEFLATED
        info.external_attr = 0o100644 << 16
        z.writestr(info, data)
tar_archive = dest / f'editcontrol-{version}.tar.gz'
with tar_archive.open('wb') as output:
    with gzip.GzipFile(filename='', fileobj=output, mode='wb', mtime=0) as gz:
        with tarfile.open(fileobj=gz, mode='w', format=tarfile.USTAR_FORMAT) as tar:
            for name, data in payload.items():
                info = tarfile.TarInfo(name)
                info.size = len(data)
                info.mode = 0o644
                info.mtime = 0
                tar.addfile(info, io.BytesIO(data))
checksums = []
for file in (archive, tar_archive):
    line = hashlib.sha256(file.read_bytes()).hexdigest() + '  ' + file.name + '\n'
    file.with_name(file.name + '.sha256').write_text(line, encoding='ascii', newline='\n')
    checksums.append(line)
    print(line.strip())
(dest / 'SHA256SUMS').write_text(''.join(checksums), encoding='ascii', newline='\n')
