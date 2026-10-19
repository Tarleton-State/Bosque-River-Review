import base64
import hashlib
import io
import tarfile
import tempfile
import unittest
from pathlib import Path

from vendor_sveltia import unpack


def package(files):
    stream = io.BytesIO()
    with tarfile.open(fileobj=stream, mode='w:gz') as archive:
        for name, content in files.items():
            member = tarfile.TarInfo(name)
            member.size = len(content)
            archive.addfile(member, io.BytesIO(content))
    data = stream.getvalue()
    integrity = 'sha512-' + base64.b64encode(hashlib.sha512(data).digest()).decode()
    return data, integrity


class VendorTests(unittest.TestCase):
    def test_integrity_checked_bundle_includes_nested_assets_and_license(self):
        data, integrity = package({
            'package/dist/sveltia-cms.js': b'/* test fixture, not the real CMS */',
            'package/dist/assets/font.woff2': b'font-fixture',
            'package/LICENSE': b'MIT test fixture',
        })
        with tempfile.TemporaryDirectory() as temp:
            destination = Path(temp)
            unpack(data, integrity, destination)
            self.assertTrue((destination / 'sveltia-cms.js').is_file())
            self.assertEqual((destination / 'assets/font.woff2').read_bytes(), b'font-fixture')
            self.assertTrue((destination / 'SVELTIA-LICENSE.txt').is_file())

    def test_modified_download_is_rejected_before_writing_files(self):
        data, integrity = package({'package/dist/sveltia-cms.js': b'fixture'})
        with tempfile.TemporaryDirectory() as temp:
            with self.assertRaisesRegex(ValueError, 'integrity'):
                unpack(data + b'tampered', integrity, Path(temp))
            self.assertEqual(list(Path(temp).iterdir()), [])

    def test_archive_traversal_is_rejected(self):
        data, integrity = package({'package/dist/../../escape.js': b'fixture'})
        with tempfile.TemporaryDirectory() as temp:
            with self.assertRaisesRegex(ValueError, 'Unsafe path'):
                unpack(data, integrity, Path(temp))
            self.assertEqual(list(Path(temp).iterdir()), [])

    def test_missing_bundle_does_not_produce_deployable_editor(self):
        data, integrity = package({'package/LICENSE': b'MIT fixture'})
        with tempfile.TemporaryDirectory() as temp:
            with self.assertRaisesRegex(ValueError, 'missing dist'):
                unpack(data, integrity, Path(temp))
            self.assertEqual(list(Path(temp).iterdir()), [])
