# NTNET service deployment

This is a deployment candidate. It requires a university administrator to install the PHP service and verify the existing authentication flow. It does not change the supplied authentication files automatically.

## 1. Check hosting requirements

Use HTTPS and PHP 8.1 or later with cURL, PDO SQLite, and fileinfo. The existing NTNET login separately requires LDAP and APCu. Permit outbound HTTPS to `api.github.com` and keep TLS certificate verification enabled.

Provide persistent, writable private storage outside the document root. SQLite is suitable for one service instance with reliable local persistent storage. Ephemeral or multiple-instance hosting needs a shared session database implementation before deployment; do not deploy independent SQLite databases behind a load balancer. Confirm this hosting capability first.

Allow JSON request bodies of at least 23 MiB at the proxy, web server, and PHP layers, with sufficient memory for decoding and encoding 15 MiB uploads (start with 256 MiB and verify). Forward the Authorization header to PHP. Allow OPTIONS requests on `api.php`. Do not cache either PHP endpoint.

## 2. Install files and private configuration

Copy only `lib.php`, `login.php`, and `api.php` from `server/cms/` to `/applications/cms/` on the university host. Keep the example configuration and tests out of the public directory.

Copy `server/cms/config.example.php` to a private PHP configuration file. Replace every placeholder with the actual absolute path. Copy `cms-schema.json` to the private `schema_file` location. Configure:

- `CMS_CONFIG_FILE`: absolute path to that private PHP configuration.
- `LDAP_AUTH_SECRET`: the same strong secret used by the existing university cookie signer and verifier, at least 32 characters.
- `CMS_BOSQUE_GITHUB_TOKEN`: administrator-managed GitHub credential, restricted to selected repositories with Contents read/write. Store the value only in the server secret environment.

The database parent directory must already exist, be outside the web root, and be writable by PHP. Use restrictive directory permissions (0700 where supported); the service creates the SQLite file with mode 0600. Protect and back up private configuration appropriately. Never commit private configuration, signing secrets, session databases, or credentials.

The included implementation uses a server-managed token, not a GitHub App. An administrator must handle credential expiration, rotation, organization authorization, and applicable branch rules. A token can cover additional selected repositories where policy allows. Editors never receive this credential. Commits record the editor’s NTNET username in their message and server audit log; GitHub author identity belongs to the service credential.

## 3. Register this website

In the private configuration, register `bosque-river-review` with:

- Repository `Tarleton-State/Bosque-River-Review`, publishing branch `main`.
- ACL label `cms_bosque_river_review`.
- Exact HTTPS editor origin, initially `https://tarleton-state.github.io` if that is its actual Pages hostname. Use its custom-domain origin if different. Do not include the repository path or a trailing slash.
- Private schema path and server credential environment-variable name.

Add a specific list to the existing `MASTER_ACL` array in `auth-config.php`:

```php
'cms_bosque_river_review' => [
    'approved_editor_username',
],
```

Existing `all` users retain access. Usernames are compared case-insensitively without the university suffix. The API checks the ACL on each authenticated request.

Set `cms-settings.json` in the repository to the matching site ID and API base URL, initially `https://www.tarleton.edu/applications/cms`. The build creates the browser’s `admin/editor-config.json`. Never put credentials in either JSON file. The private server schema is authoritative and must be updated alongside the repository schema when developers change the content model.

Project sites under the same GitHub Pages hostname share a browser origin. The origin check cannot isolate repository paths. Site-specific sessions and ACLs isolate API access; treat scripts from any site sharing that origin as trusted or use separate custom domains.

## 4. Harden the existing university login

The service invokes the existing `require_auth()` helper in a popup. That helper redirects to the existing `/applications/access.php` form. Verify the following fixes in that existing flow before production:

- Remove the hardcoded signing-secret fallback in both the cookie issuer and verifier. Require the matching environment secret. If the exposed fallback was ever active, rotate it and invalidate old cookies.
- Reject empty passwords server-side before LDAP binding. Do not rely only on the HTML `required` attribute.
- Restrict `redirect_to` to approved local application paths; reject absolute, protocol-relative, backslash, and control-character variants.
- Add login CSRF protection and configure Secure, HttpOnly, and SameSite=Lax cookies. Maintain valid LDAP TLS certificate verification.
- Correct lockout-window reset behavior after its window expires, and verify APCu availability and behavior on the actual host.

The new service refuses login if the signing-secret environment variable is absent or too short. That check does not repair the existing login’s other behavior. No NTNET password is sent to GitHub Pages or the new JSON API.

Check that site security headers permit the cross-origin popup to retain `window.opener`; an incompatible Cross-Origin-Opener-Policy can break this flow. Scope any header adjustment to the approved editor/login flow. Do not weaken global headers without review.

## 5. Verify before launch

1. Publish the repository through GitHub Actions and confirm the public site and `/admin/` load with correct assets.
2. From the actual approved HTTPS origin, sign in as an allowed test user. Confirm the popup closes and the editor loads all three sections.
3. Try an unauthorized user and confirm denial. Remove a test user from the ACL and confirm their next API request is denied.
4. Save a harmless notice change; confirm the repository commit, audit record, successful Actions run, and public update. Revert the notice using the editor.
5. Upload a test PDF and cover; create an unpublished issue, then verify publishing/hiding it and its cover description. Remove test files through an administrator afterward.
6. Open two editing tabs and verify a stale save reports a conflict. Verify expiry, sign-in recovery, logout, popup blocking, mobile layout, and keyboard access.
7. Confirm no server files or credentials appear in `_site`, the Pages artifact, browser configuration, or network responses.

Useful responses: 401 means sign in again; 403 means origin or ACL denial; 409 indicates a conflicting write; 503 means missing service configuration. GitHub credential/access failures are reported as a service error rather than exposing its response or token.

## Adding another site

Copy the website/editor/build pattern and its own content schema. Choose a unique site ID and ACL label, register its repository, exact origin, schema path, and credential reference in the shared server configuration, and add approved users to its ACL. Set the new repository’s `cms-settings.json` and Pages workflow. Repeat the launch checks. This needs one administrator registration per site; editors do not each authorize a CMS app or create tokens.

## Session protocol and limits

The login page creates a one-time, 60-second code after the existing helper approves the user. The popup returns it only to the configured origin. The editor verifies popup source, origin, state, and site, then exchanges the code with a SHA-256 PKCE verifier. The API returns an opaque 30-minute bearer session in JSON. Codes and session tokens are stored as hashes. A bare browser-provided `approved: true` is never accepted as authorization.

The service supports configured JSON files, issue collections, and media uploads. It does not support arbitrary repository paths, deletion, pull-request publishing, scheduling, or simultaneous multi-file commits. Uploads and saves are separate commits. A read is limited by GitHub’s Contents API; collection listings over 1,000 items need a different listing implementation. Build validation may reject a saved entry with missing media or a duplicate order number; the prior published site remains until a successful build.
