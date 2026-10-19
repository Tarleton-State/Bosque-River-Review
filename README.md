# Bosque River Review — NTNET mini CMS

The public website stays on GitHub Pages. A branded editor at `/admin/` edits the homepage, notice, and publication issues through forms. Editors sign in through the existing Tarleton NTNET login; they do not create GitHub tokens or need GitHub accounts.

This package uses a custom editor and PHP API. It does not use Decap, Sveltia, or Pages CMS. The original Bosque River Review logo, Tarleton logo, colors, images, and publication PDF are included.

## What goes where

| Component | Destination |
| --- | --- |
| Website source, content, editor, and Actions workflow | GitHub repository |
| Generated `_site` | GitHub Pages, deployed by Actions |
| `server/cms/lib.php`, `login.php`, `api.php` | Existing university PHP hosting |
| Server configuration, schema, session database, and GitHub credential | Private server storage/environment |

**Status: prepared and locally tested; not deployed or verified against the live university login or GitHub write API.** Follow [NTNET deployment](docs/NTNET_DEPLOYMENT.md) before enabling editing. The website can continue serving while the editing service is unavailable.

An administrator must provision one server-side GitHub credential with access to the selected repository. Organization approval and branch rules still apply to that credential. This removes token setup from editors; it does not bypass university or GitHub permissions.

## Local build

```sh
python3 -m unittest discover -s scripts -p 'test_*.py'
python3 scripts/build.py
python3 -m http.server 8000 --directory _site
```

Open `http://localhost:8000` to preview the public site. Local login is intentionally unavailable because the service accepts only the configured HTTPS editor origin.

Select **GitHub Actions** under repository Settings → Pages. The workflow validates content and publishes `_site` on pushes to `main`. CMS saves use the same workflow. Existing branch protection must permit the server integration to commit to `main`; this version does not create pull requests.

See [editor instructions](docs/EDITOR_SETUP.md), [server deployment](docs/NTNET_DEPLOYMENT.md), and [validation](docs/VALIDATION.md).
