# Bosque River Review

A static GitHub Pages publication site edited through Pages CMS.

## Content and editorial controls

- **Homepage** edits the description, logo, combined rich text introduction, optional introduction photo, and issue-list labels. Rich text is stored as HTML in `content/site.json`; the welcome message is its first paragraph.
- **Journal issues** manages PDFs, covers, display order and publication status.
- **Site identity (read only)** displays `config/branding.json`, the same source used for the header and browser title.
- **Associated site notice (read only)** displays `content/notice.json`, the same source used for the public notice.

Read-only settings apply to everyone using these CMS forms. Web Operations updates university-managed values in GitHub. These are CMS interface controls, not separate field-level GitHub permissions; users with repository write access can edit source files.

## Build and validation

Use Python 3.10 or later (Actions uses Python 3.12):

```sh
python3 -m pip install -r requirements.txt
python3 -m unittest discover -s scripts -p 'test_*.py'
python3 scripts/build.py
```

The build sanitizes introduction HTML with the pinned `nh3` package. It keeps headings (H2–H6), paragraphs, common inline formatting, lists, blockquotes and links. It removes embedded media, scripts, styles and unsafe link schemes. The public site requires no Python or CMS server.

The introduction photo and its column are omitted when the photo field is empty. A selected photo requires a description and a valid uploaded file. The published text fills the introduction width without a photo.

GitHub Actions validates pull requests. Pushes to `main` build and deploy `_site`. Saving in Pages CMS on `main` starts that publishing process; there is no separate editorial approval step.

## Updating this site

Make changes on a branch and open a pull request. Check the build before merging. The root `index.html` is a legacy reference; the deployed HTML comes from `templates/index.html` and the JSON content.

The editor update migrates the old welcome sentence into the introduction and removes duplicate identity keys from `content/site.json`. Upload the migrated JSON together with the updated form, builder, template, CSS, requirements and Actions workflow. The old builder expects the removed welcome field, so all files must be in place before the final branch checks can pass.
