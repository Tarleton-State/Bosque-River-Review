#!/usr/bin/env python3
"""Bundle an integrity-checked, pinned npm release into the Pages artifact."""

import argparse
import base64
import hashlib
import io
import json
import re
import tarfile
from pathlib import Path, PurePosixPath
from urllib.parse import urlsplit
from urllib.request import Request, urlopen

ROOT = Path(__file__).resolve().parents[1]
MAX_PACKAGE_BYTES = 50 * 1024 * 1024


def fetch(url):
    parsed = urlsplit(url)
    if parsed.scheme != 'https' or parsed.hostname != 'registry.npmjs.org':
        raise ValueError('Package downloads must use the HTTPS npm registry')
    request = Request(url, headers={'User-Agent': 'GitHub-Pages-Sveltia-Builder'})
    with urlopen(request, timeout=45) as response:
        data = response.read(MAX_PACKAGE_BYTES + 1)
    if len(data) > MAX_PACKAGE_BYTES:
        raise ValueError('Package download exceeds the size limit')
    return data


def unpack(data, integrity, destination):
    expected = 'sha512-' + base64.b64encode(hashlib.sha512(data).digest()).decode()
    if expected not in integrity.split():
        raise ValueError('npm package integrity check failed')
    files = {}
    total = 0
    with tarfile.open(fileobj=io.BytesIO(data), mode='r:gz') as archive:
        for member in archive.getmembers():
            path = PurePosixPath(member.name)
            if path.is_absolute() or '..' in path.parts:
                raise ValueError('Unsafe path in npm archive')
            if not member.isfile():
                continue
            if member.name.startswith('package/dist/'):
                relative = path.relative_to('package/dist')
                if relative.suffix == '.map':
                    continue
            elif member.name in ('package/LICENSE', 'package/LICENSE.md', 'package/LICENSE.txt'):
                relative = PurePosixPath('SVELTIA-LICENSE.txt')
            else:
                continue
            total += member.size
            if total > MAX_PACKAGE_BYTES:
                raise ValueError('Unpacked editor exceeds the size limit')
            stream = archive.extractfile(member)
            files[relative.as_posix()] = stream.read()
    if 'sveltia-cms.js' not in files:
        raise ValueError('npm package is missing dist/sveltia-cms.js')
    if 'SVELTIA-LICENSE.txt' not in files:
        raise ValueError('npm package is missing its license')
    destination.mkdir(parents=True, exist_ok=True)
    for name, contents in files.items():
        path = destination / name
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_bytes(contents)
    return files


def verify(destination, version):
    release = json.loads((destination / 'sveltia-release.json').read_text())
    if release.get('package') != '@sveltia/cms' or release.get('version') != version:
        raise ValueError('Bundled CMS version does not match sveltia-version.txt')
    files = release.get('files', {})
    if 'sveltia-cms.js' not in files or 'SVELTIA-LICENSE.txt' not in files:
        raise ValueError('Bundled CMS manifest is incomplete')
    for name, expected in files.items():
        path = PurePosixPath(name)
        if path.is_absolute() or '..' in path.parts:
            raise ValueError('Unsafe path in bundled CMS manifest')
        actual = hashlib.sha256((destination / name).read_bytes()).hexdigest()
        if actual != expected:
            raise ValueError(f'Bundled CMS file changed or is corrupt: {name}')


def vendor(destination):
    version = (ROOT / 'admin/sveltia-version.txt').read_text().strip()
    if not re.fullmatch(r'\d+\.\d+\.\d+', version):
        raise ValueError('Pin Sveltia to an exact release number')
    metadata = json.loads(fetch('https://registry.npmjs.org/@sveltia%2fcms/' + version))
    if metadata.get('name') != '@sveltia/cms' or metadata.get('version') != version:
        raise ValueError('npm returned a different package or version')
    dist = metadata['dist']
    files = unpack(fetch(dist['tarball']), dist['integrity'], destination)
    (destination / 'sveltia-release.json').write_text(
        json.dumps({'package': '@sveltia/cms', 'version': version,
                    'integrity': dist['integrity'],
                    'files': {name: hashlib.sha256(data).hexdigest()
                              for name, data in files.items()}}, indent=2) + '\n',
        encoding='utf-8',
    )
    print(f'Bundled Sveltia {version} in {destination}')


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--output', type=Path, default=ROOT / 'admin/vendor')
    parser.add_argument('--update', action='store_true', help='Download the pinned release')
    parser.add_argument('--check', action='store_true', help='Verify the included release offline (default)')
    args = parser.parse_args()
    try:
        if args.update:
            vendor(args.output)
        verify(args.output, (ROOT / 'admin/sveltia-version.txt').read_text().strip())
        print('Bundled editor verified')
    except Exception as error:
        parser.exit(1, f'Editor bundling failed: {error}\n')
