# receiptgenpro

Source for the receiptgenpro WordPress site (theme + deploy helper).

## How deployment works

1. Code is pushed to `main` on GitHub.
2. Hostinger Git auto-deploy pulls the repo into `public_html/rgp-repo`
   (blocked from the web by `.htaccess`).
3. The **RGP Deploy Helper** plugin sees the new commit (checks once a minute,
   or instantly via its webhook) and copies every folder under
   `wp-content/themes/` and `wp-content/plugins/` in this repo into the live
   `wp-content/`.

Deleting the plugin only stops step 3. Files it already copied stay in place,
so the site keeps working exactly as it was.

## Layout

```
wp-content/
  themes/<theme-folder>/          live theme (same folder name as on the server)
  plugins/rgp-deploy-helper/      deploy helper (install once from WP admin)
```

## Hostinger settings (hPanel > Websites > Advanced > Git)

| Field      | Value                                              |
|------------|----------------------------------------------------|
| Repository | `https://github.com/6977980-hash/receiptgenpro.git` |
| Branch     | `main`                                             |
| Directory  | `rgp-repo`                                         |

Then turn on **Auto Deployment** and add the webhook URL Hostinger shows to
GitHub (Settings > Webhooks).
