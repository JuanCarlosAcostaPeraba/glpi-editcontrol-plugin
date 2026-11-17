"""Validate metadata and archive structure without external dependencies."""
from pathlib import Path, PurePosixPath
import hashlib
import re
import struct
import tarfile
import xml.etree.ElementTree as ET
import zipfile
root = Path(__file__).resolve().parents[1]
version = re.search(r"define\('PLUGIN_EDITCONTROL_VERSION', '([^']+)'\)", (root/'setup.php').read_text(encoding='utf-8')).group(1)
xml = ET.parse(root/'editcontrol.xml').getroot()
assert xml.findtext('key') == 'editcontrol'
assert xml.findtext('versions/version/num') == version
assert xml.findtext('versions/version/compatibility') == '11.0.4'
assert xml.findtext('authors/author') == 'Juan Carlos Acosta Perabá'
assert xml.findtext('versions/version/download_url').endswith(f'/releases/download/{version}/editcontrol-{version}.tar.gz')
for path in ['homepage','download','issues','readme','logo','screenshots/screenshot']:
    assert xml.findtext(path).startswith('https://'), path
png=(root/'editcontrol.png').read_bytes()
assert png[:8] == b'\x89PNG\r\n\x1a\n'
assert struct.unpack('>II',png[16:24]) == (40,40)
assert (root/'docs/images/strict-mode.png').is_file()
with zipfile.ZipFile(root/f'dist/editcontrol-{version}.zip') as z:
    assert z.testzip() is None
    zipped={n:z.read(n) for n in z.namelist()}
with tarfile.open(root/f'dist/editcontrol-{version}.tar.gz') as t:
    assert all(p.isfile() for p in t.getmembers())
    tarred={p.name:t.extractfile(p).read() for p in t.getmembers()}
assert zipped == tarred, 'ZIP/tar.gz payload differs'
for name in zipped:
    parts=PurePosixPath(name).parts
    assert parts[0]=='editcontrol' and '..' not in parts
    assert not any(p in {'.git','.github','.env','tests','tools','.codebase-memory'} for p in parts)
for name in ['setup.php','hook.php','LICENSE','editcontrol.xml','editcontrol.png','public/js/editcontrol.js','public/css/editcontrol.css']:
    assert 'editcontrol/'+name in zipped, name
for line in (root/'dist/SHA256SUMS').read_text().splitlines():
    digest,name=line.split('  ')
    assert hashlib.sha256((root/'dist'/name).read_bytes()).hexdigest()==digest
print(f'PASS: catalog metadata, checksums and matching installable archives {version}')
