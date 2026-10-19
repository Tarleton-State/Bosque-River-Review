# Bosque River Review

A static publication library with browser-based editing through [Pages CMS](https://app.pagescms.org). The existing page design and Volume 1 content are retained.

## One-time setup for the repository administrator

1. Upload the contents of this folder into the root of the existing Tarleton-State/Bosque-River-Review repository on main. Do not create an extra Bosque-River-Review-main folder. Include .pages.yml and .github/workflows/pages.yml. Confirm both configuration files appear in GitHub after committing; using a local Git checkout is more reliable than a browser upload for hidden files.
2. In GitHub, open Settings → Pages → Build and deployment → Source and choose GitHub Actions. Keep any existing custom domain configuration; retain a root CNAME file if you have one.
3. Open Actions → Build and deploy website → Run workflow on main. Wait for build and deploy to succeed, then open the published site. Future commits to main trigger deployment automatically.
4. Open [Pages CMS](https://app.pagescms.org), sign in with GitHub, and follow its GitHub App installation flow. A Tarleton-State organization administrator may need to approve it. Scope installation to only this repository.
5. Select Tarleton-State / Bosque-River-Review and branch main. Confirm that Homepage, Journal issues, and Associated website notice appear.
6. Give editors the intended repository/CMS access using your organization's access process. Do not share passwords or place tokens in website files.
7. Make a small homepage edit in Pages CMS, save it, confirm the GitHub workflow succeeds, and verify the public site. This is the final end-to-end activation check.

The project is prepared locally. GitHub settings, organization app approval, editor sign-in, and the hosted CMS interface must be verified after upload. No external account connection is bundled into these files. The workflow assumes main is the publishing branch.

## Editor guide

### Change the homepage

Open Pages CMS, select the repository and main branch, and choose Homepage. Edit the labeled fields and select or upload images. Include useful image descriptions. The introduction uses plain text; add a blank line between paragraphs. Click Save. The site updates after the GitHub build and deployment finish.

### Add an issue

1. Choose Journal issues → Create.
2. Enter a title such as Volume 2.
3. Enter a unique positive whole number for Display order. Lower numbers appear first. The first published issue is labeled Current Issue, and its cover is featured.
4. Select or upload the issue PDF. Optionally upload a cover and enter its description.
5. Leave Show on the website off while preparing the entry. Save.
6. To publish a new current issue, first change the previous current issue's order from 1 to 2 (and move other numbers as needed), then set the new issue to 1 and turn on Show on the website. Save.
7. Wait for deployment and check both the list link and cover link.

For a new archived issue, use a larger unused order number. To remove an issue from the list, switch off Show on the website and save. Do not delete PDFs or images still used by published entries.

Drafts control listing visibility, not confidentiality. Repository files and uploaded media can remain publicly accessible. Only upload material suitable for public access. Uploading an accessible PDF remains the editor's responsibility.

### Change the notice

Choose Associated website notice, make the approved wording change, and save.

### If a saved change does not appear

Open GitHub's Actions tab. If the build failed, read the Build and validate content error. Common causes are a missing PDF, missing cover description, deleted image, or duplicate display-order numbers. Correct the field and save again. A failed build does not replace the last successful deployment. If no workflow runs, confirm the edit was saved to main and Actions is enabled.

## Developer reference

- .pages.yml: CMS fields, image library, PDF library.
- content/site.json: homepage content.
- content/notice.json: associated website wording.
- content/issues/*.json: issue entries.
- templates/index.html: page markup.
- css/style.css: site design.
- scripts/build.py: dependency-free Python 3.10+ static builder.
- .github/workflows/pages.yml: pull-request validation and main deployment.
- _site/: generated public website; ignored by Git.

Build locally:

    python3 scripts/build.py
    python3 -m http.server 8000 --directory _site

Open http://localhost:8000. Generated asset links are relative and work under a GitHub project path such as /Bosque-River-Review/ as well as a custom domain.

Only _site is deployed. It contains rendered HTML, styles, images, PDFs, and the favicon. Content JSON, templates, and CMS configuration are excluded from the artifact. Existing uploaded media is copied to preserve direct file URLs.

The root index.html is an initial static snapshot for reference. Publishing builds from content and templates; do not edit the root index.html to change the website. The public page needs no content-fetch script, database, server login, or API token.

The CMS uses structured forms rather than a drag-and-drop layout builder. Saves on main publish automatically; this setup does not add an editorial approval stage. Branch protection requiring pull requests needs an additional CMS workflow decision before direct editing on main can work.

References: [Pages CMS quick start](https://pagescms.org/docs/quick-start/), [configuration](https://pagescms.org/docs/configuration/), and [GitHub Pages workflows](https://docs.github.com/en/pages/getting-started-with-github-pages/using-custom-workflows-with-github-pages).
