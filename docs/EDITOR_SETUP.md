# Editor setup and daily use

## One-time GitHub access

Each editor needs their own GitHub account and Write, Maintain, or Admin access to this repository. An organization team can grant that access. This CMS does not create a separate editor-only GitHub permission level.

For this direct-publishing setup, create a fine-grained personal access token:
1. In GitHub account Settings, open Developer settings → Personal access tokens → Fine-grained tokens → Generate new token.
2. Give it a recognizable name and an expiration that follows university policy.
3. Set Resource owner to the organization owning the site repository.
4. Choose Only select repositories, then select this site's repository.
5. Under Repository permissions, grant Contents: Read and write. Metadata: Read is included automatically. Pull requests permission is unnecessary for this package's simple workflow.
6. Generate the token. If the organization requires approval, wait for approval before using it.
7. Open the published site's /admin/ page, choose Sign In with Token, and enter it there. Use the token option, not an OAuth login service.

Never put the token in a file, commit, URL, repository variable, or shared document. Each person should use their own token. Replace it when it expires or is revoked; repository access is still required.

Sveltia stores the token in the browser's local storage. Use a trusted computer/profile and sign out when finished on a shared device. Project sites under the same github.io hostname share a browser origin; use narrowly scoped tokens and trusted projects, or distinct custom domains when site-to-site isolation is required.

GitHub can require approval or restrict token types. This setup removes the need to install a CMS OAuth/GitHub app, but it does not override the organization's access policy. If the organization is unavailable as Resource owner, confirm membership and policy with its owner.

## Edit the homepage

Choose Website content → Homepage. Update the fields and select/upload images. Include image descriptions. The introduction is plain text; use blank lines between paragraphs. Save/publish the changes using the editor controls, then wait for the GitHub deployment to complete.

## Add or update an issue

1. Open Journal issues and create an entry.
2. Enter an Issue title.
3. Set a unique positive whole-number Display order. The lowest number among listed entries becomes Current Issue.
4. Select/upload the Issue PDF. A cover is optional; provide Cover description if you use one.
5. Leave Show on the website off to keep the entry out of the public list. Saving still writes the entry to the repository.
6. Turn Show on the website on when ready and save.
7. Check the live PDF and cover links after the build finishes.

When adding a new current issue, first move existing display orders upward to leave the lowest number free. You can also use spaced order values such as 10, 20, 30 and give a newer issue 5. Do not reuse an order already assigned to a listed issue.

Unlisted is not private. Uploaded media and repository files can be publicly accessible. PDFs should be accessible before upload. Avoid removing assets used by other entries.

## Notice

Open Website content → Associated website notice. Use approved wording and save.

## Troubleshooting

- Sign-in denied: check token expiry, organization approval, repository selection, and your own repository access.
- Read-only editor: verify Contents write access and branch protection. Simple publishing needs permission to commit directly to main.
- PDF/image rejected by the build: select a file stored in the repository's issues/ or images/ folder. External file URLs are not accepted by this site's builder.
- Changes missing: check the Actions tab. A failed build preserves the last successful public deployment.
- Editor does not load: confirm the build completed successfully and that /admin/vendor/sveltia-cms.js exists on the published site.
- Page appears stale after a deployment: refresh after confirming both build and deploy succeeded.

This package saves directly to main. It does not add a separate review/approval step. Branch protection and editorial-workflow requirements should be settled before onboarding editors.

Official instructions:
https://sveltiacms.app/en/docs/backends/github
https://docs.github.com/en/authentication/keeping-your-account-and-data-secure/managing-your-personal-access-tokens
