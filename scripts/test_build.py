"""Run with python3 -m unittest discover -s scripts -p 'test_*.py'."""

import json
import shutil
import tempfile
import unittest
from html.parser import HTMLParser
from pathlib import Path
from urllib.parse import unquote

import build


class Links(HTMLParser):
    def __init__(self):
        super().__init__()
        self.urls = []

    def handle_starttag(self, tag, attrs):
        self.urls.extend(value for key, value in attrs if key in ('href', 'src'))


class BuildTests(unittest.TestCase):
    def setUp(self):
        self.original = build.ROOT
        self.temp = tempfile.TemporaryDirectory()
        self.root = Path(self.temp.name) / 'project'
        shutil.copytree(self.original, self.root, ignore=shutil.ignore_patterns('_site', '__pycache__'))
        build.ROOT = self.root

    def tearDown(self):
        build.ROOT = self.original
        self.temp.cleanup()

    def save(self, name, data):
        (self.root / name).write_text(json.dumps(data), encoding='utf-8')

    def issue(self):
        return json.loads((self.root / 'content/issues/volume-1.json').read_text())

    def test_complete_page_and_project_relative_assets(self):
        output = self.root / '_site'
        build.build(output)
        html = (output / 'index.html').read_text()
        self.assertIn('Current Issue', html)
        self.assertIn('Volume 1', html)
        self.assertNotIn('<script', html)
        parser = Links()
        parser.feed(html)
        for url in parser.urls:
            if not url.startswith('#'):
                self.assertFalse(url.startswith('/'), url)
                self.assertTrue((output / unquote(url)).is_file(), url)
        self.assertFalse((output / 'content').exists())
        self.assertFalse((output / '.pages.yml').exists())

    def test_ordering_and_incomplete_draft(self):
        first = self.issue()
        first['order'] = 20
        self.save('content/issues/volume-1.json', first)
        second = dict(first, title='Volume 2', order=10)
        self.save('content/issues/volume-2.json', second)
        self.save('content/issues/draft.json', {'title': 'Secret draft', 'published': False})
        html = build.render()
        self.assertLess(html.index('Volume 2'), html.index('Volume 1'))
        self.assertNotIn('Secret draft', html)
        self.assertEqual(html.count('<span>Current Issue</span>'), 1)

    def test_empty_list_and_no_cover(self):
        issue = self.issue()
        issue['cover'] = ''
        self.save('content/issues/volume-1.json', issue)
        self.assertIn('without-cover', build.render())
        issue['published'] = False
        self.save('content/issues/volume-1.json', issue)
        html = build.render()
        self.assertIn('New issues will be listed', html)
        self.assertNotIn('<figure', html)

    def test_plain_text_is_escaped_and_paragraphs_render(self):
        data = json.loads((self.root / 'content/site.json').read_text())
        data['title'] = '<script>alert(1)</script>'
        data['introduction'] = 'A & B\n\nSecond paragraph'
        self.save('content/site.json', data)
        html = build.render()
        self.assertNotIn('<script>', html)
        self.assertIn('&lt;script&gt;', html)
        self.assertIn('<p>A &amp; B</p>', html)
        self.assertIn('<p>Second paragraph</p>', html)

    def test_broken_media_and_duplicate_order_stop_publish(self):
        issue = self.issue()
        self.save('content/issues/duplicate.json', dict(issue, title='Another'))
        with self.assertRaisesRegex(ValueError, 'different display order'):
            build.render()
        (self.root / 'content/issues/duplicate.json').unlink()
        issue['pdf'] = '/issues/missing.pdf'
        self.save('content/issues/volume-1.json', issue)
        with self.assertRaisesRegex(ValueError, 'does not exist'):
            build.render()

    def test_unsafe_paths_and_cover_description(self):
        for path in ('https://example.com/file.pdf', '/issues/../README.md', 'javascript:alert(1)'):
            with self.assertRaises(ValueError):
                build.asset(path, 'issues', {'.pdf'})
        issue = self.issue()
        issue['cover_alt'] = ''
        self.save('content/issues/volume-1.json', issue)
        with self.assertRaisesRegex(ValueError, 'cover_alt'):
            build.render()


if __name__ == '__main__':
    unittest.main()
