# Validation record

Validated locally against this package:

- Seven Python tests covering public-page generation, local media, editor configuration, server exclusion, issue ordering, drafts, empty publications, optional covers, escaping, and invalid content.
- Thirty PHP assertions covering authorization, normalization, content paths and field validation, one-time PKCE exchange, replay rejection, hashed sessions, site isolation, session expiry, and PHP syntax parsing.
- Fourteen PHP endpoint checks covering login request validation, PKCE exchange, replay, authenticated session access, cross-site denial, CORS denial, preflight, and logout revocation, using a fixture identity helper.
- JavaScript syntax check and a visual browser review of the branded login screen.

The PHP assertions ran in a PHP 8.4 WebAssembly runtime. They do not verify the production PHP extensions, server headers, actual LDAP bind, existing cookie issuer, GitHub writes, or a deployed Pages workflow. Follow the deployment guide’s live checklist before launch.

To repeat locally with standard PHP installed:

```sh
python3 -m unittest discover -s scripts -p 'test_*.py'
python3 scripts/build.py
php server/tests/run.php
```

The GitHub Actions workflow runs the Python tests and build. It does not run the PHP service tests. The PHP code belongs on the university server and is excluded from the public artifact by the build.

The optional HTTP endpoint harness uses Node.js and a development-only PHP WebAssembly runtime. It creates a disposable in-memory filesystem and a fixture identity helper; it makes no LDAP or GitHub requests. To repeat:

```sh
npm --prefix server/tests install --ignore-scripts
npm --prefix server/tests test
```

These packages are for tests only and are not included in the website build or PHP deployment.
