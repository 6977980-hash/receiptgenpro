# receiptgenpro

Source for the receiptgenpro WordPress site (theme + deploy helper).

## How deployment works

1. Code is pushed to `main` on GitHub.
2. Hostinger Git auto-deploy pulls the repo into
   `public_html/wp-content/plugins/rgp-deploy-helper`, so the repo itself is the
   deploy helper plugin (folder is blocked from the web by `.htaccess`).
3. The **RGP Deploy Helper** plugin sees the new commit (a WP-Cron check once
   a minute, a check when an admin opens wp-admin, or instantly via its signed
   GitHub webhook; never inside a visitor's page request) and copies every
   folder under `wp-content/themes/` and `wp-content/plugins/` in this repo
   into the live `wp-content/`. Each folder is built in a staging copy and
   swapped in with `rename()`; the previous copy stays as `.rgp-prev-<name>`
   for **Tools > Deploy Helper > Roll back the last deploy**.

Deleting the plugin removes this checkout and stops step 3. Files it already
copied into the live `wp-content/` stay, so the site keeps working exactly as
it was.

## Checks

CI (`.github/workflows/ci.yml`) runs `php -l` on every PHP file, the PHP tests
in `tests/php/`, `node --check` on the theme's scripts and the JS tests. Locally:

```
npm i --no-save jsdom@26.1.0   # once, for the DOM tests
node --test tests/*.test.js     # Node 22, as in CI
for f in tests/php/*-test.php; do php "$f"; done
```

## Layout

```
rgp-deploy-helper.php             the deploy helper plugin (activate once in WP admin)
wp-content/
  themes/<theme-folder>/          live theme (same folder name as on the server)
  plugins/<other-plugin>/         optional extra plugins to deploy
```

## Hostinger settings (hPanel > Websites > Advanced > Git)

| Field      | Value                                              |
|------------|----------------------------------------------------|
| Repository | `https://github.com/6977980-hash/receiptgenpro.git` |
| Branch     | `main`                                             |
| Directory  | `public_html/wp-content/plugins/rgp-deploy-helper` |

Then turn on **Auto Deployment** and add the webhook URL Hostinger shows to
GitHub (Settings > Webhooks).
