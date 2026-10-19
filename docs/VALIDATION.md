# Validation

Verified locally on October 19, 2026:
- Twelve automated checks pass: complete static output and local asset paths, automatic repository selection for template copies, token-only configuration, safe repository names, issue ordering, unlisted/incomplete entries, empty-list handling, optional covers, escaped text and paragraphs, missing media, duplicate orders, missing cover descriptions, npm package integrity, archive traversal, packaged dependencies, and missing editor distributions.
- The included Sveltia 0.239.0 distribution was downloaded from the npm registry and verified against npm's published SHA-512 integrity.
- The included editor and license pass the local per-file integrity manifest checks.
- The site builds with no CMS server variables or external authentication service.
- Browser check: the real bundled editor loads /admin/config.yml without configuration errors and displays Sign In Using Access Token. This caught and corrected the Decap-specific allow_multiple option during migration.
- Browser check: the public page loads with its original publication content, descriptions, notice, and Volume 1 links.
- Editor configuration fields match the existing homepage/notice JSON. Issue ordering is stored as an integer.
- The GitHub Actions workflow and CMS YAML parse successfully.
- The final source archive includes .github/workflows/pages.yml and the actual editor distribution, not just a CDN link.

Not performed:
- A real GitHub token sign-in, repository save/upload, or GitHub Pages deployment. These require the editor's authorized account and repository setup.
- A full accessibility audit of the CMS or publication PDF.

Complete the first live edit-and-publish check after upload. A passing local build does not establish that organization token policies or branch protections permit editing.
