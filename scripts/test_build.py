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

    def test_identity_is_escaped_and_rich_text_paragraphs_render(self):
        data = json.loads((self.root / 'content/site.json').read_text())
        branding = json.loads((self.root / 'config/branding.json').read_text())
        branding['title'] = '<script>alert(1)</script>'
        self.save('config/branding.json', branding)
        data['introduction'] = '<p>A &amp; B</p><p>Second paragraph</p>'
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

    def test_optional_photo_and_conditional_description(self):
        data = json.loads((self.root / 'content/site.json').read_text())
        for missing in ('', None):
            data['hero'] = missing
            data.pop('hero_alt', None)
            self.save('content/site.json', data)
            html = build.render()
            self.assertIn('class="introduction without-photo"', html)
            self.assertNotIn('class="river-image"', html)
        data.pop('hero')
        self.save('content/site.json', data)
        self.assertIn('without-photo', build.render())
        data['hero'] = '/images/BRR-logo_180x64.jpg'
        self.save('content/site.json', data)
        with self.assertRaisesRegex(ValueError, 'hero_alt'):
            build.render()
        data['hero_alt'] = 'Description with "quotes" & symbols'
        self.save('content/site.json', data)
        html = build.render()
        self.assertIn('class="introduction"', html)
        self.assertIn('alt="Description with &quot;quotes&quot; &amp; symbols"', html)

    def test_rich_text_formatting_and_safe_links(self):
        content = ('<h2>About us</h2><p><strong>Bold</strong> and <em>italic</em></p>'
                   '<ul><li>One</li></ul><ol><li>Two</li></ol><blockquote>Quote</blockquote>'
                   '<a href="https://example.edu/about?x=1&amp;y=2">Website</a>'
                   '<a href="mailto:editor@example.edu">Email</a>')
        html = build.rich_text(content)
        for fragment in ('<h2>About us</h2>', '<strong>Bold</strong>', '<em>italic</em>',
                         '<ul><li>One</li></ul>', '<blockquote>Quote</blockquote>',
                         'href="https://example.edu/about?x=1&amp;y=2"',
                         'href="mailto:editor@example.edu"'):
            self.assertIn(fragment, html)

    def test_rich_text_removes_active_content_and_unsafe_links(self):
        payloads = [
            '<script>alert(1)</script>',
            '<iframe src="https://example.com">embed</iframe>',
            '<svg><a xlink:href="javascript:alert(1)">bad</a></svg>',
            '<img src=x onerror="alert(1)">',
            '<style>body {display:none}</style>',
            '<a href="javascript:alert(1)" onclick="alert(2)">link</a>',
            '<a href="jav&#x61;script:alert(1)">link</a>',
            '<a href="data:text/html,bad">link</a>',
            '<p style="position:fixed" id="main-content">text</p>',
        ]
        for payload in payloads:
            with self.subTest(payload=payload):
                html = build.rich_text('<p>Keep this</p>' + payload)
                self.assertIn('<p>Keep this</p>', html)
                for forbidden in ('<script', '<iframe', '<svg', '<img', '<style',
                                  'javascript:', 'data:', 'onclick=', 'onerror=',
                                  'style=', 'id='):
                    self.assertNotIn(forbidden, html)
        for empty in ('<p></p>', '<p><br></p>', '<p>&nbsp;</p>',
                      '<p>&#160;</p>', '<script>alert(1)</script>'):
            with self.assertRaisesRegex(ValueError, 'introduction'):
                build.rich_text(empty)

    def test_single_source_identity_and_combined_introduction(self):
        data = json.loads((self.root / 'content/site.json').read_text())
        branding = json.loads((self.root / 'config/branding.json').read_text())
        self.assertNotIn('welcome', data)
        self.assertNotIn('title', data)
        self.assertNotIn('university_name', data)
        html = build.render()
        self.assertIn(branding['title'], html)
        self.assertEqual(html.count('You have reached the online library for this publication.'), 1)
        data['title'] = 'Unauthorized replacement'
        data['university_name'] = 'Unauthorized university'
        self.save('content/site.json', data)
        self.assertNotIn('Unauthorized', build.render())

    def test_branding_directory_is_protected_from_build_output(self):
        with self.assertRaisesRegex(ValueError, 'separate build directory'):
            build.build(self.root / 'config')


if __name__ == '__main__':
    unittest.main()
