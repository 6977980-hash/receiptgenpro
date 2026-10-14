# ReceiptGenPro theme — Changelog

## 1.1.0 — 26 Sept 2026 (SEO / GEO / AEO audit fixes)

After updating: go to **Tools → ReceiptGenPro Fixes** and run sections 1–5 (Preview, then Apply).

### Generator (C3, C4, M5)
- Each of the 13 templates now has its own form (inc/category-data.php → `form`): extra fields, labels, placeholders, receipt title, tip, amount paid + balance due, amount in words, signature line, donation statement.
- JPEG download added (the site promised PDF, PNG *or JPEG*).
- jsPDF + html2canvas are lazy-loaded on the first Download click (~550 KB less JS per page view); PDFs use JPEG inside (~5 MB → ~130 KB).
- Mobile overflow of the form at 375px fixed.
- Issuer-only usage note under the generator.

### Technical SEO (inc/seo-fixes.php)
- One robots meta tag (core wp_robots removed) and one canonical (core rel_canonical removed).
- No canonical on 404 / search pages.
- Sitemap: tag archives removed; categories with <4 posts excluded (matches their noindex).
- /receipt-templates/ hub page (page-receipt-templates.php), auto-created; breadcrumbs, header, footer and content links point to it.
- /llms.txt; IndexNow (key file + ping on publish).
- /ads.txt is a real 404 until an AdSense ID is set (was an empty 200 at /ads.txt/).
- Security headers (nosniff, Referrer-Policy, X-Frame-Options, Permissions-Policy, HSTS on HTTPS, X-Powered-By removed).
- REST /wp/v2/users hidden from visitors; author archives 301 → /about-us/; author links → /about-us/.
- Runtime fixes: /blog/<slug>/ and /#templates links rewritten; leftover "[Site owner…]" / "[add the date…]" notes stripped.

### On-page / schema
- Homepage title shortened (55 chars); consistent post titles; unique descriptions for About/Contact/Privacy/Terms/Cookie; real category descriptions + intros.
- Schema: SearchAction removed; homepage Breadcrumb "Home > Home" removed; homepage FAQPage (from the same array as the visible FAQ); WebApplication on every template; CollectionPage + ItemList + FAQ on the hub; Article author → Organization for team accounts; Article `citation`; HowTo no longer printed; empty social `sameAs` hidden; Organization email + contactPoint.
- Emoji moved out of H3 headings; "Genuinely Free" → "100% Free".

### Trust / E-E-A-T
- "Key facts & official sources" box on 21 guides (IRS, HMRC, CRA, ATO, CBIC/GST — verified 26 Sept 2026), inc/post-sources.php.
- Author box: "Written by" (a "Reviewed by" line only when custom field `rf_reviewed_by` is set).
- Blog intro no longer claims a "finance team".
- Social profile constants emptied until real profiles exist.
- Cookie banner only shown when AdSense or GA4 is configured; optional consent-gated GA4 (`RF_GA4_ID`) with download events.

### Database tools (Tools → ReceiptGenPro Fixes)
1. Backdated publish dates → real dates (16–19 Sept 2026).
2. Placeholder text on About/Contact/Terms/Privacy/Cookie; issuer framing on Taxi/Hotel/Restaurant/Rent pages.
3. Stored /blog/ and /#templates links.
4. Email-based author slug.
5. Editorial Policy draft.

## 1.1.1 — 26 Sept 2026
- 3 new templates: Rent Receipt for HRA (India, INR default, month field, landlord PAN), Payment Receipt, Deposit Receipt (inc/category-data.php + pages).
- `currency` option per template; `month` field type printed as "April 2026".
- Hub page: new templates + comparison rows; description updated to 16 templates.
(Applied on the live site through Appearance → Theme File Editor on 26 Sept 2026.)

## Technical SEO fixes — 14 Oct 2026 (audit phase 2)
No CSS/JS or rewrite-rule changes, so the theme version is unchanged.
- Blog and category/tag page 2+ canonicalize to themselves and get " – Page N" in title and description (SEO-01).
- Tag archives are always `noindex, follow`; term names are capitalized in titles and breadcrumbs (SEO-03).
- JSON-LD text is entity-decoded (headline, description, WebPage name, breadcrumbs) (SEO-04).
- Template pages use the H1 label ("Rent Receipt Generator") as breadcrumb and WebPage name (SEO-05).
- JSON-LD encoded with `JSON_HEX_TAG` (SEC-03).
- Unknown 32-hex `.txt` URLs return a real 404 (SEO-02); `/site.webmanifest` no longer redirects to a trailing slash (SEO-06).
- Hub FAQ: deposits point to /deposit-receipt/, general payments to /payment-receipt/ (CNT-01).
- `article:published_time`/`modified_time` only on posts; `og:image:width/height` for the default image (SEO-07).
- Article `citation` honours the per-post `rf_sources` custom field (WP-04).
