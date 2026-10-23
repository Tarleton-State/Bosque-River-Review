#!/usr/bin/env python3
"""Build the static site using Python 3.10+ and requirements.txt."""

import argparse
import json
import shutil

import nh3
from html import escape, unescape
from pathlib import Path
from string import Template
from urllib.parse import quote, unquote, urlsplit

ROOT = Path(__file__).resolve().parents[1]
IMAGE_EXTENSIONS = {'.jpg', '.jpeg', '.png', '.webp', '.gif', '.svg', '.avif'}


def text(data, key):
    value = data.get(key)
    if not isinstance(value, str) or not value.strip():
        raise ValueError(f'{key}: enter a nonempty text value')
    return value.strip()


def asset(value, folder, extensions):
    """Accept local CMS paths and escape once, including existing encoded spaces."""
    if not isinstance(value, str) or not value.strip():
        raise ValueError(f'Select a file from {folder}')
    parsed = urlsplit(value)
    if parsed.scheme or parsed.netloc or parsed.query or parsed.fragment:
        raise ValueError(f'Use an uploaded {folder} file, not an external URL: {value}')
    relative = unquote(parsed.path).lstrip('/')
    path = Path(relative)
    if '..' in path.parts or not path.parts or path.parts[0] != folder:
        raise ValueError(f'File must be inside {folder}: {value}')
    resolved = (ROOT / path).resolve()
    if not resolved.is_relative_to((ROOT / folder).resolve()) or not resolved.is_file():
        raise ValueError(f'File does not exist: {value}')
    if path.suffix.lower() not in extensions:
        raise ValueError(f'Unsupported file type: {value}')
    return quote(path.as_posix(), safe='/')


def load(path):
    data = json.loads(path.read_text(encoding='utf-8'))
    if not isinstance(data, dict):
        raise ValueError(f'{path.name}: expected a JSON object')
    return data


def rich_text(value):
    """Keep editorial formatting; strip embeds, scripts, styles and unsafe links."""
    cleaned = nh3.clean(
        value,
        tags={'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'del',
              'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li',
              'blockquote', 'a', 'code', 'pre', 'hr', 'sub', 'sup'},
        attributes={'a': {'href', 'title'}},
        clean_content_tags={'script', 'style', 'iframe', 'object', 'svg', 'math'},
        url_schemes={'http', 'https', 'mailto', 'tel'},
    )
    # Do not publish an empty editor document or content removed by sanitization.
    visible = unescape(nh3.clean(cleaned, tags=set(), attributes={}))
    if not visible.strip():
        raise ValueError('introduction: enter some text')
    return cleaned


def render():
    site = load(ROOT / 'content/site.json')

    # Use the university-managed publication and university names.
    branding = load(ROOT / 'config/branding.json')
    for key in ('title', 'university_name'):
        site[key] = text(branding, key)
    
    values = {key: escape(text(site, key)) for key in (
        'title', 'university_name', 'description', 'logo_alt',
        'documents_heading', 'empty_message',
    )}
    values['logo'] = asset(site.get('logo'), 'images', IMAGE_EXTENSIONS)
    values['hero_image'] = ''
    if site.get('hero'):
        hero = asset(site['hero'], 'images', IMAGE_EXTENSIONS)
        hero_alt = escape(text(site, 'hero_alt'))
        values['hero_image'] = f'<img class="river-image" src="{hero}" alt="{hero_alt}">'
    values['introduction_class'] = 'introduction' + (
        '' if values['hero_image'] else ' without-photo'
    )
    values['introduction'] = rich_text(text(site, 'introduction'))
    notice = load(ROOT / 'content/notice.json')
    values['notice_heading'] = escape(text(notice, 'heading'))
    values['notice_text'] = escape(text(notice, 'text'))

    issues = []
    for source in sorted((ROOT / 'content/issues').glob('*.json')):
        issue = load(source)
        published = issue.get('published', False)
        if not isinstance(published, bool):
            raise ValueError(f'{source.name}: published must be true or false')
        # Incomplete drafts may be saved without blocking public-site builds.
        if not published:
            continue
        order = issue.get('order')
        if isinstance(order, bool) or not isinstance(order, int) or order < 1:
            raise ValueError(f'{source.name}: display order must be a positive whole number')
        issue['title'] = text(issue, 'title')
        issue['pdf_url'] = asset(issue.get('pdf'), 'issues', {'.pdf'})
        if issue.get('cover'):
            issue['cover_url'] = asset(issue['cover'], 'images', IMAGE_EXTENSIONS)
            issue['cover_alt'] = text(issue, 'cover_alt')
        issues.append(issue)
    issues.sort(key=lambda item: (item['order'], item['title'].casefold()))
    if len({item['order'] for item in issues}) != len(issues):
        raise ValueError('Published issues must have different display order numbers')

    rows = []
    for index, issue in enumerate(issues):
        label = 'Current Issue' if index == 0 else escape(issue['title'])
        rows.append(
            f'<li><a href="{issue["pdf_url"]}" '
            f'aria-label="Read {escape(site["title"])} — {escape(issue["title"])} (PDF)">'
            '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 5h7l2 2h9v12H3z"/></svg>'
            f'<span>{label}</span><span class="link-label">{escape(issue["title"])} · PDF</span>'
            '</a></li>'
        )
    values['issue_list'] = (
        '<ul class="document-list">\n' + '\n'.join(rows) + '\n</ul>'
        if rows else f'<p>{values["empty_message"]}</p>'
    )
    current = issues[0] if issues else None
    values['cover'] = ''
    if current and current.get('cover_url'):
        values['cover'] = (
            '<figure class="cover">'
            f'<a href="{current["pdf_url"]}" aria-label="Read '
            f'{escape(site["title"])} — {escape(current["title"])} (PDF)">'
            f'<img src="{current["cover_url"]}" alt="{escape(current["cover_alt"])}">'
            '</a></figure>'
        )
    values['library_class'] = 'publication-library' + (
        '' if values['cover'] else ' without-cover'
    )
    return Template((ROOT / 'templates/index.html').read_text(encoding='utf-8')).substitute(values)


def build(output):
    output = output.resolve()
    # Refuse source folders, their parents and their descendants as output targets.
    protected = [(ROOT / name).resolve() for name in ('config', 'content', 'templates', 'scripts', 'images', 'issues', 'css', 'js', '.github')]
    if output == ROOT.resolve() or ROOT.resolve().is_relative_to(output) or any(
        output.is_relative_to(path) or path.is_relative_to(output) for path in protected
    ):
        raise ValueError('Output must be a separate build directory, such as _site')
    html = render()  # Validate before touching the previous output.
    output.mkdir(parents=True, exist_ok=True)
    for name in ('css', 'images', 'issues'):
        target = output / name
        if target.exists():
            shutil.rmtree(target)
        shutil.copytree(ROOT / name, target)
    shutil.copy2(ROOT / 'favicon.svg', output / 'favicon.svg')
    # Only generated public files are deployed; CMS drafts and source stay out.
    (output / 'index.html').write_text(html, encoding='utf-8')
    (output / '.nojekyll').touch()
    cname = ROOT / 'CNAME'
    if cname.is_file():
        shutil.copy2(cname, output / 'CNAME')
    print(f'Built {output}')


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--output', type=Path, default=ROOT / '_site')
    args = parser.parse_args()
    try:
        build(args.output)
    except (ValueError, OSError) as error:
        parser.exit(1, f'Build failed: {error}\n')
