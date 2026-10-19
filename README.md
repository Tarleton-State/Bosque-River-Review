# Bosque River Review — GitHub Pages + Sveltia CMS

A publication website with its own visual editor at /admin/. Pages, content, PDFs, images, editor configuration, and deployment workflow live together in this repository. No university web-server connection, PHP application, LDAP gateway, or OAuth service is part of this setup.

## Start here

1. Upload this folder's CONTENTS into the root of Tarleton-State/Bosque-River-Review on main. Include the hidden .github folder. If upgrading an earlier package, remove .pages.yml, admin/bootstrap.js, and docs/SHARED_AUTH.md from GitHub if they remain; a browser upload does not delete old files.
2. Open repository Settings → Pages. Under Build and deployment, choose GitHub Actions as Source. Retain existing custom domain settings and any CNAME file.
3. Open Actions → Build and deploy website → Run workflow on main. Wait for both jobs to succeed.
4. Open your published website with /admin/ appended. For the standard project address: https://tarleton-state.github.io/Bosque-River-Review/admin/. If you use a custom domain, use that site's /admin/ instead.
5. Follow docs/EDITOR_SETUP.md to create your own repository-scoped GitHub token and sign in.

The build detects the repository it runs in, so a new repository created from this template automatically points its deployed editor to that new repository.

## What the editor can change

- Publication title, university name, search description, introduction, logo, and photo.
- Journal issues with PDFs, covers, descriptions, and list ordering.
- The associated website notice.

Editors work with labeled forms. The current layout stays controlled by templates and CSS. This is a structured content editor, not a drag-and-drop page builder.

## How publishing works

Sveltia commits content and media using the editor's GitHub access. Commits to main trigger GitHub Actions, which validates content, builds the public page, copies the included CMS release, and publishes _site to Pages.

The lowest display-order number among listed issues is Current Issue and supplies the featured cover. Each listed issue needs a different positive whole-number order. An unlisted issue is excluded from the page but its uploaded media and repository data are not confidential.

A failed build leaves the last successful deployment in place. Check Actions for a missing file, invalid order, or missing image description, correct it in the CMS, and save again.

## Reuse as a template

After uploading this project, enable Settings → General → Template repository. For another publication:
1. Choose Use this template → Create a new repository.
2. Enable Actions if GitHub asks, then select GitHub Actions under Pages settings.
3. Run the workflow on main.
4. Give its editors repository access and have them configure personal tokens for that repository.
5. Open /admin/ and replace the sample publication content and assets.

The workflow assumes the publishing branch is main. For a different branch, update the workflow triggers and backend.branch in admin/config.yml. The publication layout and content model are reused; a different type of website may need different fields and templates.

## Self-contained runtime

The editor software is INCLUDED in admin/vendor/ and served from GitHub Pages with the site. Builds verify its version and file hashes, then copy it into the public artifact. They do not fetch the CMS from a CDN or npm. The browser communicates with GitHub's API to read and save content.

GitHub Actions itself still downloads its standard actions and Python runtime. There is no separate CMS server, university-hosted login service, or CMS vendor account to operate.

The upstream CMS is currently beta. This package pins version 0.239.0 in admin/sveltia-version.txt and includes the upstream license. To update deliberately, change that version, run the updater described below, test the editor, and commit the updated vendor files and manifest together.

## Local development

Python 3.10+ is required; no pip dependencies are needed.

    python3 -m unittest discover -s scripts -p 'test_*.py'
    python3 scripts/build.py
    python3 scripts/vendor_sveltia.py --check
    python3 -m http.server 8000 --directory _site

These commands build and verify the included files offline; the local static server is for browser preview. Visit http://localhost:8000 for the page, or /admin/ for the editor. Signing in with a token still writes to the configured remote repository even when the editor is opened locally.

For an intentional CMS version update only:

    python3 scripts/vendor_sveltia.py --update

The updater downloads the pinned release from registry.npmjs.org, verifies its published SHA-512 integrity, retains the distribution and license, and records hashes for build-time checks. It needs HTTPS connectivity and a valid Python CA trust store; certificate verification stays enabled. Commit admin/sveltia-version.txt and admin/vendor/ after testing the upgrade.

Set GITHUB_REPOSITORY=owner/repository when building locally for a different repository; otherwise the local build defaults to Tarleton-State/Bosque-River-Review. GitHub Actions supplies this variable automatically.

## File map

- admin/config.yml: visual editor fields, token sign-in, repository and branch.
- admin/index.html: local editor shell.
- admin/sveltia-version.txt: exact bundled CMS version.
- content/: editable JSON and issue entries.
- templates/index.html and css/style.css: presentation.
- scripts/build.py: static page build and repository detection.
- scripts/vendor_sveltia.py: offline integrity checks and optional release updater.
- .github/workflows/pages.yml: validation, build, and deployment.
- docs/EDITOR_SETUP.md: account, token, and publishing instructions.
- docs/VALIDATION.md: verification performed and remaining live checks.

Only _site is deployed. Private credentials never belong in the repository, workflow, or public output. Source JSON and guides are excluded from the Pages artifact, but remain visible if the repository is public. Existing media is copied to preserve direct URLs. Root index.html is a reference snapshot; edit content through the CMS or source JSON and rebuild.

Reference: https://sveltiacms.app/en/docs/backends/github
