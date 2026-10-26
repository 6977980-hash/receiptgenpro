# ReceiptGenPro theme — Changelog

## 1.4.3 — 26 Oct 2026 (Google Analytics 4)

- **GA4 on**: `RF_GA4_ID` in `inc/adsense.php` is `G-T47P9EX906`, so the existing consent gate turns on: Consent Mode v2 defaults everything to denied in `<head>`, the cookie banner shows, and gtag.js loads only after Accept (or on later visits once accepted). Receipt download clicks are sent as a `receipt_download` event with the format only. Nothing loads on `/verify-receipt/`. The CSP (report-only) already allows www.googletagmanager.com and `connect-src https:`.
- **Policies** (`rf_v143_patches()`, one-time migration `rf_migration_v143` through `wp_update_post()`, then LiteSpeed purge): the privacy policy no longer says no analytics runs; it names Google Analytics as a service that receives the IP address, its cookies, the download count, the opt-out, and "Analytics Now, Ads Later". The cookie policy becomes "Cookies We Use" and lists `_ga` and `_ga_T47P9EX906` (up to 2 years). Both get a new "Last updated" date.
- Tests: `tests/php/v143-test.php` (the ID, the consent gate, the CSP, every patch applies once to the live text of 26 Oct in curly and straight quotes, and the migration is registered).

## 1.4.2 — 25 Oct 2026 (audit of 25 Oct, v2 Phase 6: rent receipt laws by city and country)

- **Massachusetts page** (G-6): `/rent-receipt-laws/massachusetts/`, from M.G.L. c. 186, § 15B read on malegislature.gov: the security deposit receipt and the receipt for last month's rent paid in advance (at the time of payment), the bank receipt within 30 days, what each must show, the deposit record, and the penalties the text gives (immediate return of the deposit, treble damages under § 15B(6)–(7)). The entry is flagged `deposit_only`: the page carries the deposit receipt generator, the FAQ says there is no rule for monthly rent, and the hub keeps it out of the monthly rent table and its count, under "States With a Receipt Rule for Deposits Only" (now a link). `rf_rent_deposit_only_laws()` is derived from the flag.
- **Ohio city rules**: Ohio Revised Code Chapter 5321 (codes.ohio.gov) has no rent receipt duty, so `/rent-receipt-laws/ohio/` says so and lists the cities; `/ohio/columbus/` (Columbus City Code § 4551.05, Ord. 0496-2021, passed 8 March 2021: a receipt for every rent payment and deposit, within 4 business days for cash; fourth-degree misdemeanor) and `/ohio/cincinnati/` (Cincinnati Municipal Code § 871-9(a)(7), as restated by Ord. 419-2021: a signed receipt for the deposit and all rent once a deposit is paid, except personal checks). Both ordinances were read in the cities' own Legistar records (the code sites block automated reading). New `rf_rent_receipt_city_laws()` and `rf_rent_city_rule_states()`. Nested paths, not `columbus-ohio`, because the breadcrumb then reads Rent Receipt Laws / Ohio / Columbus from the page tree, and the draft pass needed one change either way.
- **UK and Canada** (new `rf_rent_receipt_intl_laws()`, `rf_rent_law_countries()`): `/rent-receipt-laws/uk/` with a section per nation from legislation.gov.uk: England (Landlord and Tenant Act 1985 ss. 4–7, rent book for weekly rent), Wales (s. 4(4): not for Renting Homes (Wales) Act contracts, and no rule found in that Act), Scotland (cash rent receipt, Private Residential Tenancies (Statutory Terms) (Scotland) Regulations 2017; rent books for older weekly assured and protected tenancies) and Northern Ireland (cash receipt, Private Tenancies (NI) Order 2006 art. 5, in force 1 April 2023). `/rent-receipt-laws/canada/` lists Ontario (RTA 2006 s. 109, free receipt on request), British Columbia (RTA s. 26(2), cash rent), Manitoba (RTA s. 57, cash rent) and Quebec (Civil Code art. 1568, a general rule, flagged `general`), each with its own page, plus Newfoundland and Labrador (deposit only), Alberta and New Brunswick (no rule found) and the provinces we could not read (Nova Scotia, PEI, Saskatchewan, the territories: not listed). The generator there uses GBP or CAD (CAD gets Letter paper by `paperFor()`), the landlord field reads "Landlord or agent", no US requirement check is preselected, and the page text says law, landlord, tenant and rent book.
- **One list for every law page**: `rf_rent_law_entries()` normalizes states, Ohio, cities, countries and provinces (kind, name, parent, template, purpose, currency); `rf_feature_pages()` creates every page from it (parents first), and the template, the FAQ (`rf_rent_laws_faqs( $sub )`, also the FAQPage schema), the WebPage citations (`rf_rent_law_citations()`) and the orphan redirect use it. `rf_draft_removed_law_pages()` now checks every page under the hub at any depth by its full path.
- **Hub**: a "City Rules" table, an "Other Countries" list, an FAQ on other countries, and "We list a state or city only after reading its statute or ordinance". The existing state pages are unchanged apart from Massachusetts and Ohio in "Other States" and "an Oregon". llms.txt names the cities and countries. Review date 2026-10-25.
- New pages are created by `rf_ensure_feature_pages()` on the next wp-admin visit or setup run, and IndexNow is pinged as each one is published.
- Tests: `tests/php/v142-test.php` (every new entry has a citation, the URL read, a trigger, a summary and a date; metas ≤ 155; every path in `rf_feature_pages()` with its parent first; unique paths; the draft pass at depth; the hub tables and count; each page's template, currency, FAQ, disclaimer and wording) and `tests/php/render-rent-law.php` (renders any law page without WordPress). Checked in Chromium (Playwright) at 375 and 1366 px on the hub, Massachusetts, Ohio, Columbus, Cincinnati, the UK, Canada, each province, Texas and New York: no sideways scroll, no console errors, the right template and currency; PDFs are Letter for USD and CAD, A4 for GBP, with the receipt text.

## 1.4.1 — 25 Oct 2026 (audit of 25 Oct, v2 Phase 5: new documents)

- **HRA: receipts for several months in one PDF** (G-4): on `/hra-rent-receipt/` (and on `/` with the HRA type) a "Receipts for several months (one PDF)" panel: From and To month (default April to March of the current Indian financial year; at most 12, with a note when more are chosen), a monthly rent (empty = the receipt's total, shown as the placeholder), the receipt date (1st or last day of each month) and "Download all as one PDF". Each month is the form's receipt with its own `rent_month`, one item "House rent for <Month YYYY>", its own date, a consecutive number from the current one (`receiptNumbers()`, through `nextReceiptNumber()`: RENT-0007 → RENT-0007 … RENT-0018) and its own QR code. The months are drawn one at a time with the existing html2canvas export (progress "Making 4 of 12…"), each canvas freed after use, each month on its own page (`paperSize( paperChoice() )`, A4 for INR; an 80 mm page per slip with the Thermal design) with that month's invisible text layer. File `hra-rent-receipts-<from>-<to>.pdf`; after the download the last number is remembered (`rgp-last-receipt-number`) and the field moves on to the next one. A landlord-PAN note (no block) when 12 × rent is above ₹1,00,000 and the PAN is empty, worded like the HRA check in `inc/requirements.php`; an empty landlord name or a zero rent gets the 1.3.8 download check. The month fields don't count as a change to the single receipt, whose PDF, PNG and JPEG are unchanged. `savePdf()` is split into `createPdf()` / `addReceiptPages()` so both paths share the page logic.
- **Year-end donation statement** (G-7): a "Document" menu on `/donation-receipt/`: "Single gift receipt" (default, the 1.4.0 output byte for byte, tested against the 1.4.0 script) or "Year-end statement (all gifts from one donor)". A statement has a Tax year (default last year from January to March, this year from April), a gift date under each row (the `rf-item-hsn` pattern: shown only in this mode, saved with the form), the title "ANNUAL DONATION STATEMENT", "Gifts received: 1 Jan to 31 Dec <year>", a Date / Description / Amount table with dates in the chosen format, in-kind rows with "No value stated", and only "Total cash contributions". The goods-or-services statement and the quid pro quo line cover the whole statement ("these contributions"): IRS Publication 1771 allows one acknowledgment such as an annual summary for several contributions and asks for a description and good-faith estimate of what was given in return, which the existing fields hold for the statement; a donor can name the gift it relates to in the description. The QR code gets `st: <year>`, no payment method and no gift type; `/verify-receipt/` shows "Annual statement <year>" and "Total cash contributions". The donation check's "Date of the contribution" is met by a date on every gift. `/donation-receipt/` gets the FAQ "Can I give a donor one statement for the whole year?" (Pub 1771: annual summary allowed, gifts under $250 not added together, received by the filing or due date, charities usually send by January 31) from the one-time migration `rf_migrate_v141_content()` (option `rf_migration_v141`, find copied from the live REST content of 25 Oct); the FAQPage schema follows the visible FAQ.
- **Refund receipt / credit note** (G-9): new category `refund-receipt` (page from `rf_feature_pages()`, template `page-receipt-category.php`, article from `rf_refund_receipt_page_content()`: what it is, what to include, how to fill it in, 4 FAQs), written for the business that gives the refund. The form: Document "Refund receipt" (default) or "Credit note"; Original receipt / invoice #, Original receipt date, Reason, Refund method (the payment method field is hidden); amounts entered as positive numbers and printed without minus signs as "Refunded item" lines, "Tax refunded (8%)" and "Total refunded" (or "Total credited"); no tip and no amount paid. The QR code carries `rf: 1` (refund) or `rf: 2` (credit note); the verify page prints "Refund" / "Credit note", "Tax refunded" and "Total refunded" / "Total credited", with the same sum check. In GST bill mode (INR with tax, or a GSTIN) a note under the Document menu, never on the receipt, says it is not a GST credit note: section 34 of the CGST Act, 2017, rule 53(1A) of the CGST Rules and rule 48(4) e-invoicing, read in CBIC's copies on 25 Oct 2026. New requirement check `refund` (rule 53(1A) details that fit any refund; flags an empty original receipt number). `refund-receipt` is in the related lists of the payment, cash and freelance receipts, and appears by itself in the homepage picker, footer, 404 page, llms.txt, hub, schema, sitemap and `/embed/refund-receipt/`.
- No new storage key (the new fields are kept with the form in sessionStorage).
- Tests: `tests/v141-new-documents.test.js` (month range and max 12, dates, financial year, numbering; a 12-month PDF with 12 pages, each month's text and QR code, the remembered number, Thermal pages, the notes and the download check, the homepage type; statement dates, formats, cash-only total, in-kind rows, QR `st`, checker, single mode equal to 1.4.0; refund and credit note labels, QR `rf`, the GST note, the verify page) and `tests/php/v141-test.php` (the category, page and links, form keys, the PHP and JS GST note, the FAQ patch on the live text once, the migration). Checked in Chromium (Playwright) at 375 and 1366 px with this branch's markup, CSS and JS and the real cdnjs libraries: `/`, `/hra-rent-receipt/`, `/donation-receipt/`, `/refund-receipt/`, `/embed/refund-receipt/` have no sideways scroll and no console errors; the 12-month PDF has 12 A4 pages and `pdftotext` finds all 12 months and RENT-0007 to RENT-0018; Thermal gives 12 pages of 80 mm; statement, refund and credit note PDFs carry their labels; single PDF, PNG and JPEG keep one number.

## 1.4.0 — 25 Oct 2026 (audit of 25 Oct, v2 Phase 4: saved business profile and more currencies)

- **Remember my business details** (G-1): a checkbox under "Business details", off by default. Ticked, it keeps `{ v: 1, name, address, contact, taxIdLabel, taxId, logo, currency [, code, dp] }` in localStorage `rgp-business-profile`, saved straight away and again whenever typing pauses (the existing `saveFormSoon` timing). Unticking deletes the key; "Forget saved details" (shown only while a profile exists) deletes it and unticks the box but leaves the fields on screen. On every generator page (the homepage, every template, so a donation form fills the same "Organization" field) the profile fills the seller fields, the logo and the currency when this tab has no draft for the page; a draft is newer and wins, and the box is ticked whenever a profile exists. A page or type with its own currency (INR on the HRA form) keeps it and doesn't save it into the profile. Filling from the profile, or ticking the box, never moves the receipt number on (`rf-remember` is not part of the form's saved fields or of the "receipt changed" check). Every storage call is wrapped: a logo over the quota is saved without the logo and a one-line note says so; private mode or a full storage only shows a note. Inside `/embed/<slug>/` the checkbox isn't rendered and the key is never read or written.
- **More currencies** (G-5): NZD `NZ$`, SGD `S$`, ZAR `R`, NGN `₦`, PHP `₱`, MYR `RM` (letters, so "RM 12.50"), JPY `¥` with 0 decimals and KWD `KWD` with 3 (1,000 fils). `rf_currencies()` takes the decimals as an optional third entry; `rf_currency_decimals()` sends them to the generator (`rfReceipt.decimals`) and the verify page (`rfVerify.decimals`). Amounts in words: New Zealand / Singapore dollars and cents, rand and cents, naira and kobo, Philippine pesos and centavos, ringgit and sen, yen (no subunit), Kuwaiti dinars and fils. ₦ and ₱ render in the receipt fonts and in html2canvas; the PDF text layer writes them as "NGN" / "PHP" (outside Latin-1, like ₹). Default date format: day first for NZD, SGD, ZAR, NGN, MYR and KWD, 2026-10-25 for JPY, and month first for PHP (the Philippines writes the month first, so not day first as the handoff listed). Paper: A4 for all of them (Letter stays USD and CAD only).
- **Minor units**: `toMinor( n, dp )` (`toCents()` is `toMinor( n, 2 )`), and `lineCents`, `calcTotals`, `formatAmount` and `amountInWords` take the active currency's decimals, default 2, so every 2-decimal figure is byte-for-byte as before (all older tests pass unchanged). The caps per unit (`maxMinor()`) stay safe integers (999,999,999,999.990 at 3 decimals). The price, tip, amount paid, gift value and fixed-discount inputs step by 1, 0.01 or 0.001 with the currency. ¥1,234 × 3 at 10% is ¥3,702 + ¥370 = ¥4,072 with no ".00" anywhere; KWD 1.250 × 2 at 5% is 2.500 + 0.125 = 2.625, "Two Kuwaiti dinars and six hundred twenty-five fils only" (the handoff's "one hundred twenty-five fils" was the tax line, 0.125).
- **QR code and verify page**: amounts stay integers in the minor unit; the payload gets `dp` right after `cu` only when the decimals aren't 2, so a 2-decimal receipt encodes exactly as before and every older code reads the same. `verify-receipt.js` uses `dp`, else the currency's decimals, else 2, in `money()`; the "don't add up" check was already in minor units, so its tolerance is now one cent, yen or fils.
- **Other (type a code)…**: the last currency option shows a 3-letter code field (`^[A-Za-z]{3}$`, upper-cased) and a decimals menu (2 / 0 / 3). The receipt prints the code before each amount ("CHF 1,000.00"), the words read "One thousand CHF only" ("… and fifty cents" with 2 decimals, "… and 125/1000" with 3), the QR code carries `cu: "CHF"` and `dp` when not 2. The code and decimals are kept with the form (sessionStorage) and in the profile.
- **Text**: the privacy and cookie policies list `rgp-business-profile` (opt-in, what it holds, never uploaded, how to remove it) and get a new "Last updated" date; About Us ("What We Never Store") says the same. One-time migration `rf_migrate_v140_content()` (option `rf_migration_v140`, finds copied from the live REST content of 25 Oct), registered in `inc/setup-tasks.php`. The homepage FAQ "Can I add my own logo?" and the templates hub FAQ mention the checkbox.
- Tests: `tests/v140-profile-currencies.test.js` (minor-unit helpers, JPY / KWD / custom code on the form and in the QR code, the verify page with and without `dp`, profile restore on another page and type, a draft beating the profile, untick / Forget, no number bump, quota and no-storage notes, the embed) and `tests/php/v140-test.php` (the patches apply once to the live text, the migration is registered, the currency list); CI now also runs `tests/php/v139-test.php`. Checked in Chromium (Playwright) at 375 and 1366 px on `/`, `/rent-receipt/`, `/hra-rent-receipt/`, `/donation-receipt/`, `/cash-receipt/` and `/embed/rent-receipt/` with this branch's markup, CSS and JS: the profile (logo included) comes back in a new browser context with the same storage on other receipt types, Forget works, the embed has no checkbox and leaves the key alone, JPY / KWD / CHF / NGN / PHP / MYR PNG and PDF downloads carry the right decimals (`pdftotext`), the QR link opens on `/verify-receipt/` with the same figures, no sideways scroll, no console errors and no CSP report-only violations.

## 1.3.9 — 25 Oct 2026 (audit of 25 Oct, v2 Phase 3: PDF, speed and security)

- **PDF paper size** (A25-M4): a "PDF paper size" menu above the download buttons: Automatic (US Letter for USD and CAD, A4 for every other currency), US Letter or A4. The choice is saved with the form (sessionStorage, no new storage key) and doesn't count as a change to the receipt, so its number stays. Letter is 215.9 × 279.4 mm with the receipt 195.9 mm wide inside 10 mm margins and 259.4 mm of page; A4 stays 190 × 277 mm. The page height in canvas px comes from `paperSize()` instead of the old fixed 190 / 277, so long receipts split into Letter pages the same way. `paperSize()` / `paperFor()` are pure helpers for the HRA 12-month PDF later. The Thermal slip stays 80 mm wide.
- **Searchable PDF** (G-3): the visible page is still the image (it matches the preview exactly); page 1 also carries the receipt's text in invisible ink (`renderingMode: 'invisible'`, built-in Helvetica): business and its details, title, number, date, customer and the other details, each item row (name, qty, price, total), subtotal, discount, taxes, tip, total, amount paid and balance, the amount in words and the statement. It is read from the rendered preview (first copy only on "Office + Customer Copy") and shrunk to fit page 1 on long receipts. Helvetica has only Latin-1, so a currency symbol outside it is written as the code ("INR 25,000.00", "EUR 5.00"; "£" and "$" stay) and text that still can't be encoded (Hindi, Arabic) is left out, not printed as junk. The PDF's title is "Receipt <number>", its subject the business name, its creator ReceiptGenPro. `pdftotext` now finds the number and total; the Thermal PDF gets the text too.
- **Embed layout shift** (A25-L11): on `/embed/<template>/` the empty preview holds 640 px (the templates draw 525-706 px at 768-1366 px) until the script draws the first receipt, and the grey box shown while the QR library loads now has the size the code will have (`qrModulesFor()`: the QR version for the link's length at level L, then `qrPixels()`), instead of 112 px for a 123-147 px code.
- **Content-Security-Policy-Report-Only** (A25-L13): `rf_security_headers()` sends a full policy in report-only mode on front-end HTML (`rf_is_frontend_html()`: not wp-admin, REST, AJAX, XML-RPC, feeds, sitemaps or robots.txt). It allows the site itself, cdnjs, and the AdSense and Google tag hosts for when ads and analytics are switched on; `frame-src` also has `'self'` for the live preview on `/receipt-generator-widget/` (the handoff's list lacked it). `frame-ancestors *` on valid `/embed/` paths (where the enforcing `frame-ancestors *` header stays), `'self'` elsewhere. No `report-uri`: violations show in the browser console. Switching it to enforcing is a later, separate step once it has run clean.
- **Checks** (A25-L12): the README and CI run `node --test tests/*.test.js` (`node --test tests/` fails on Node 22), and CI uses Node 22.
- Tests: `tests/v139-pdf-paper-text.test.js` (A4 / Letter numbers, default by currency, Latin-1 text, QR box size, Letter and A4 PDFs with their text and properties, the menu saved and not changing the number, a 60-item receipt over 3 Letter pages with all the text on page 1, the Thermal slip) and `tests/php/v139-test.php` (the policy and where it is sent). The jsPDF stand-ins in two older tests gained the text methods. Checked in Chromium (Playwright) against the live pages with this branch's files: PDF, PNG and JPEG on `/`, `/rent-receipt/` and `/hra-rent-receipt/` at 375 and 1366 px carry the same number; USD PDFs are 612 × 792 pt, INR 595.3 × 841.9; `pdftotext` finds the number and total; the Thermal PDF is 80 mm (226.8 pt) wide; no console errors and no CSP report-only violations on `/`, `/hra-rent-receipt/`, a blog post, `/verify-receipt/`, `/receipt-generator-widget/` and `/embed/rent-receipt/`.

## 1.3.8 — 25 Oct 2026 (audit of 25 Oct, v2 Phase 2: generator on phones, tax)

- **Phones** (A25-M5): under 900 px a bar at the bottom of the screen shows "Total: …" and a "Preview & download" button that scrolls to the receipt. It shows only while the form is on screen and the preview and download buttons are not (IntersectionObserver), sits above the cookie banner when that shows, and leaves room for `env(safe-area-inset-bottom)`. Its total is `aria-hidden` (the existing live region already reads the total); the button is a normal link. The empty preview says "Add items in the form to see them here".
- **Thermal Slip** (A25-M5): words wrap whole ("deliverable", not "deliverab / le"), the headers have a gap, and the Price and Total columns are as wide as the longest amount (`--rf-amount-ch`, set by the script, up to 10 characters), so the description keeps about half the slip at 375 px and in the 380 px export. Longer amounts wrap after a thousands comma (`<wbr>`). `hyphens: auto` is not used: html2canvas draws the hyphenated split without its hyphen, which is the mid-word break this fixes.
- **Prices that include tax** (A25-M3): "Prices include tax" in More options. The total is subtotal − discount + tip, each tax is worked back out (`taxable × rate / (100 + all rates)`, in cents) and printed as "Includes VAT (20%) £20.00" under the total; any rounding cent stays in the net, so net + taxes = gross. The QR code carries `ti: 1` and the verify page prints "VAT (20%, incl.)". Hidden and ignored on no-tax forms (donations, missing receipts).
- **GST bill mode** (G-2): on an INR receipt that charges tax, or when the tax number type is GSTIN (never on a no-tax form, so not on the HRA rent receipt), each item gets an optional HSN/SAC box (printed under the item name, saved with the form, in the QR code only while it fits) and the form an optional "Place of supply" (printed with the receipt details and in the QR code). Choosing GSTIN names empty tax names CGST and SGST and splits a single rate between them. Nothing calls the receipt a tax invoice.
- **"Generated with ReceiptGenPro"** (A25-L1): a checkbox under the QR option, on by default and saved with the form; unticked, the line is not printed.
- **Empty receipts** (A25-L5): downloading with no business name or a total of 0 (an in-kind gift with no value is fine) first shows a notice above the download buttons with "Download anyway" and "Go back" (no `alert` / `confirm`); "Download anyway" holds for that receipt, so its other formats don't ask again. A cleared date prints "Date: —". The HRA form's Landlord PAN shows a hint (not a block) when it doesn't look like ABCDE1234F, and is turned into capitals.
- **Currencies** (A25-L3): SAR and AED use their letter codes ("SAR 1,000.00"; '﷼' was the Iranian rial sign and 'د.إ' turned the amount right to left). A symbol made of letters gets a space; "$", "€", "CA$" and "AU$" don't. Same on the verify page.
- **Date format** (A25-L4): a "Date format" menu (25 Oct 2026, 10/25/2026, 25/10/2026, 2026-10-25), by default following the currency (USD month first; INR, PKR, GBP, AUD, AED and SAR day first; the rest "25 Oct 2026"). It applies to the receipt date and to date extras (rent period, check-in…); the QR code keeps ISO dates. The choice is saved with the form (sessionStorage, no new storage key).
- **Verify page** (A25-L6): when the lines − discount + added taxes + tip differ from the total by more than a cent (and every line fit in the code), a neutral note says "The lines in this code don't add up to the total shown." A code without a currency prints "12.00 (no currency)".
- Tests: `tests/v138-generator-ux-tax.test.js` (inclusive VAT and CGST/SGST maths and rounding, GST fields, HSN in and out of the QR code, footer toggle, empty-download notice, cleared date, PAN hint, SAR/AED, date formats, verify sum note, phone bar markup, Thermal CSS). Two older tests follow the new behaviour (a saved item has `hsn`; a download test names the business). Checked in Chromium (Playwright) at 375 and 1366 px on `/`, `/hra-rent-receipt/`, `/donation-receipt/` and `/restaurant-receipt/`: no sideways scroll, no console errors, the bar shows and hides, PDF and PNG carry the same number, the Thermal PNG has no split words.

## 1.3.7 — 25 Oct 2026 (audit of 25 Oct, v2 Phase 1: content, trust and SEO)

- **Donation guidance** (A25-H3): `/donation-receipt/` and the nonprofit donation guide follow IRS Publication 1771: describe an in-kind gift without valuing it (the donor values it); a gift is fully deductible when the donor got nothing in return, otherwise only the amount above a good-faith estimate of the benefit. The FAQPage schema follows, since it is built from the visible FAQ.
- **Privacy and cookie policies** (A25-M6): split into "What Happens Today" (no cookies from our code; the browser storage keys `rgp-design`, `rgp-last-receipt-number` and `rgp-form:<path>`; Hostinger CDN / LiteSpeed and cdnjs (Cloudflare) receive the visitor's IP) and "If We Turn On Ads or Analytics" (the AdSense / DART / opt-out text, behind the cookie banner). "Last updated" changes to the day the patch runs.
- **Who checks the facts** (A25-M7): new section on `/editorial-policy/` (sources re-checked on the stated date; no accountant or lawyer reviews the guides). Posts with a Sources box show "Sources checked <date>" in the byline.
- **Guides** (A25-L7): GST guide gains e-invoicing (IRP / IRN, ₹5 crore threshold), place of supply and reverse charge, and cites the e-invoice portal; the UK VAT guide notes voluntary registration; rent-law pages say "Checked against the statute text in October 2026".
- **Rent receipt** (A25-L2): the Indian "Landlord PAN" extra is gone from the US rent form (the HRA form keeps its own); new optional "Received by (name and title)" field, and the `rent-us` check asks for it (NY RPL §235-e), with the signature kept as a reminder.
- **Feeds** (A25-L10): `/comments/feed/` and `/<slug>/feed/` redirect (301) to `/feed/`.
- **Schema** (A25-L8): Organization and WebSite have `alternateName` "Receipt Gen Pro" and "receiptgenpro.com".
- The page text changes run once from `rf_migrate_v137_content()` (option `rf_migration_v137`, admin or WP-Cron, with revisions). Each patch matches straight or curly quotes, and does nothing if its text is gone. Tests: `tests/php/v137-test.php` applies every patch to the 25 Oct page text (`tests/php/fixtures/v137/`).

## 1.3.6 — 25 Oct 2026 (audit of 25 Oct, Phase 1: receipt tools)

- **Receipt numbers** (A25-H1): a download no longer moves the number on. A PDF, PNG and JPEG of the same receipt all carry the same number; the first change to the receipt after a download (any field except the number, the design, the logo or the QR option; an empty new item row doesn't count) moves the field to the next number, once, and only if it still holds the downloaded number. The last downloaded number is still kept in `localStorage` (`rgp-last-receipt-number`), so a reload starts after it. The hint under the field says so.
- **Seller details** (A25-H2): every form, including the homepage picker, has a "Business details (optional)" group under the business name: address, phone / email / website, and a tax number with its type (Tax ID, VAT No., GSTIN, ABN, EIN, NTN, TRN). They print under the business name in all nine designs. The tax number is never put in the QR code; the address goes in it as an extra, clipped to 60 characters. The "Place of the expense" check (`expense` purpose) now counts this address, so restaurant, payment and other receipts can pass it.
- **Donation receipts** (A25-M2): new form keys `no_tax` (donation and missing-receipt forms: no tax rate, discount or second tax controls, and any rates are ignored; the homepage picker hides and shows the controls when switching type) and `in_kind` (an item with a name and no price prints "No value stated" instead of $0.00). Two donation fields, "Goods or services given in return" and "Their estimated value", print a quid pro quo disclosure in place of the "No goods or services" statement.
- **QR caption** (A25-M1): "Scan to view the details in this code" (was "Scan to verify this receipt"); the image's alt text is "QR code with this receipt's details". The QR code stays on by default (owner decision).
- Tests: `tests/v136-receipt-tools.test.js` (jsdom: three downloads with one number, then the next; typed number kept; seller details printed and escaped, tax number not in the decoded QR, address clipped; expense check on the restaurant receipt; donation totals, QPQ line, "No value stated"; picker General → Donation → General; QR caption) and new checks in `tests/php/requirements-test.php`.

## 1.3.5 — 20 Oct 2026 (audit of 20 Oct, Phase 5: content and optional fixes)

- **About Us** (#62): "What We Never Store" names all three libraries: qrcode-generator loads with the generator while the QR code option is on (the default), html2canvas and jsPDF only on a download. The live page gets the new line from a one-time patch (`rf_migrate_v135_content()`, option `rf_migration_v135`, run from admin or WP-Cron with a revision); the stale comment in `inc/performance.php` is fixed.
- **HRA sources** (#63): from 1 April 2026 the Income-tax Act, 2025 applies. The HRA check now cites Schedule III (Table: Sl. No. 11) of that Act (was s. 10(13A) of the 1961 Act) and the Income Tax Department's Form No. 124 FAQ for the ₹1 lakh landlord-PAN rule (Form 124 replaced Form 12BB); the intro says the exemption is only in the old regime (s. 202(2)). Read in the Gazette of India copy of the Act.
- **Rent laws** (#65): the hub counts only landlord-specific rules; California (Civ. Code § 1499, a general debtor rule) is listed and named separately (`'general' => true`). Virginia's statement of charges on written request (§ 55.1-1204(D)) is a separate "on request" note, not a receipt content. Both re-read on the official text.
- **Placeholders** (#65): "Rent for October 2026", "INV-2026-…" and the other dated examples follow the current month and year.
- **Before ads / analytics go live** (#66; nothing changes while both IDs are empty): Google Consent Mode v2 default-denied stub before any Google tag; the stored cookie choice is versioned (`granted@2`); a footer "Cookie settings" button reopens the banner, and withdrawing reloads the page; no AdSense or GA4 on `/verify-receipt/` (its URL carries the receipt). The theme has no fixed ad slots (Auto ads), so there is no slot to reserve.
- **jsPDF 4.2.1** (#68, was 2.5.1) from cdnjs with its SRI; A4 and thermal PDF and PNG exports tested in Chromium.
- **Hygiene** (#70): founder name, title and LinkedIn are `RF_FOUNDER_*` constants in `functions.php`; the unused HowTo extraction code is gone; one currency list, `rf_currencies()` (`inc/category-data.php`), feeds the menu, the receipt (symbol read from the menu label) and the verify page (`rfVerify.symbols`); `rf_default_primary_menu()` and `rf_reading_time()` moved from `header.php` / `single.php` to `functions.php`; README sentence fixed.
- **Tests** (#69): `tests/v135-dom.test.js` runs the real markup (`tests/php/render-generator.php`) and scripts in jsdom: render, type switch (HRA → INR → USD), save and restore, receipt number, verify page. `tests/v135-content.test.js`; deploy guard checks (settle wait, `index.lock`, mass delete) in `tests/php/deploy-test.php`; HRA and rent-law checks in `tests/php/requirements-test.php`. CI runs ESLint (`eslint.config.js`, bug rules only) and `node --test tests/` (directory form; on Node 21+ use `node --test`).

## 1.3.4 — 20 Oct 2026 (audit of 20 Oct, Phase 4: accessibility, performance, deploy)

- **Contrast** (#45): green text (`--rf-success`: requirement checker "done", author reviewer) is #15803d (5.0:1, was 3.15:1); the Modern Blue receipt label is white on blue (was #dbeafe, 4.24:1).
- **Links in text are underlined** (#46): `p a`, `li a`, field hints, sources notes, law disclaimers and the QR hint, not only post and page content. Menus, breadcrumbs and link lists keep their look.
- **Scrolling** (#50): no smooth scrolling with "reduce motion" on; `scroll-padding-top: 80px` so a jump link doesn't land under the header.
- **Screen readers** (#51): item fields are named per row ("Item 2 price", "Remove item 2"); the requirement checker has a `role="status"` summary read once typing pauses; switching receipt type is announced; the second copy of "Office + Customer Copy" is hidden from screen readers; on the verify page only the status line is live; footer headings are h2 (no h3 without an h2); the mobile menu is a `<nav>`; the `/embed/` page has `<main>` and a hidden h1; Download buttons say `aria-busy` while preparing instead of being disabled, so keyboard focus stays on them.
- **Cookie banner** (#52): `role="region"` named "Cookie consent", and the page gets bottom padding while it shows, so it never covers the footer.
- **QR code** (#53): redrawn at most every 300 ms while typing (the last change always lands; a download redraws it first).
- **Logo** (#60): only a `data:image/(png|jpeg|gif|webp);base64` URL is used, also from the tab's saved form, and the `<img>` is built with the DOM, not HTML text. Other image types (SVG) are redrawn as PNG on upload.
- **Setup work leaves visitor requests** (#47, #55, new `inc/setup-tasks.php`): creating the tool pages, the one-time content migration and the rewrite flush run on `admin_init` or in a WP-Cron event scheduled when one is out of date. `rf_feature_pages_hash` is saved only when every page exists; the migration records each finished page and resumes a run older than 10 minutes. The only rewrite flush left is the per-version one; the favicon and ads.txt one-shot flushes are gone.
- **Fewer queries** (#54): the three flags read on every request are autoloaded; related posts use `no_found_rows`.
- **Script loading** (#49): only the theme's own scripts are deferred, through WordPress 6.3+'s loading strategy (a filter on the same handles for older versions). Other plugins' scripts are no longer given `defer`.
- **Images** (#58): homepage blog cards load lazily; related-post images have a `sizes` that matches their ~240 px.
- **IndexNow** (#59): pings only on a first publish (or a re-publish) or when the title, text, excerpt or slug changed.
- **HSTS** (#61): `includeSubDomains` (the only subdomains are www, on HTTPS, and ftp).
- Tests: `tests/v134-a11y.test.js` (logo check, hidden copy, contrast, reduced motion) and `tests/php/deploy-test.php` (in CI).

### Deploy helper (`rgp-deploy-helper.php` 1.2.0)
- **No deploy in visitor requests** (#48): a WP-Cron event checks once a minute (in its own background request), wp-admin checks once a minute for admins, and the webhook deploys at once.
- **Staged swap** (#48): each theme/plugin folder is built in `.rgp-stage-<name>` (repo files plus live files the helper never deployed) and swapped in with two `rename()` calls; the old folder stays as `.rgp-prev-<name>`, and **Tools → Deploy Helper → Roll back the last deploy** swaps it back (and forward again). If staging is not possible, files are copied in place in the order sub-folders → `functions.php` → templates.
- **Safer removal** (#56): an empty repo is never deployed; folders emptied by a removal are deleted; symlinks, `..` paths and anything resolving outside the repo are skipped, and a delete must stay inside the live `wp-content`; changing the repo folder resets the stored file list.
- **Webhook** (#57): POST only; a GitHub webhook whose Secret is the deploy key is checked with `X-Hub-Signature-256`; `X-RGP-Key` still works; `?key=` still works for now but shows a warning on the admin page, which no longer shows a URL with the key. GitHub's ping is answered; a delivery while Git is still writing gets 202 and the minute check deploys it. The answer is a short summary, not the file list.
- Phase 1 guards stay: the 60 s settle wait, the mass-delete check, the atomic lock.

## 1.3.3 — 20 Oct 2026 (audit of 20 Oct, Phase 3: generator and UX)

- **Huge, exponent and Infinity inputs** (#23): amounts are capped at 999,999,999,999.99 and tax / discount rates at 100 %, in the inputs' `max` and in the script; `1e400` counts as 0. A note under the form says when an entry was capped, a negative number was counted as 0, or a discount larger than the subtotal was cut (#31). Rates print trimmed (8.875 %, not 8.875000000001 %).
- **Receipt layout:** the Qty / Price / Total headers are right-aligned over their numbers in all nine designs (#24); long business names and big amounts wrap inside the receipt at 375 px (fixed table columns, smaller numbers on phones) (#26).
- **Printing** (Ctrl+P) prints only the receipt, without header, form, buttons, checker, footer or cookie banner, with colours kept; the Thermal Slip prints on an 80 mm page as long as the slip (#25).
- **Verification QR:** the payload stays under ~500 characters (texts, then extra fields, then item names, then items are shortened or dropped; text is cut by code point), and the code is drawn at a whole number of px per module, so it scans when printed (#27). It now carries the discount, tax lines and tip, which the verify page shows (#37), and never the PAN, EIN or other tax IDs (#38).
- **Maths and words:** unit prices are rounded to cents before × quantity (#28); "One US dollar", "one cent", "one penny", no "Zero … and" (#29); thousands separators everywhere, lakh / crore grouping for INR and PKR (#30).
- **Requirement checker:** the restaurant and payment receipts get an address field, so "Place of the expense" can pass (#32); amount limits apply only to a receipt in their own currency (the HRA limits are in rupees), and the HRA PAN limit is compared per month for a From–To rent period (#33). `tests/php/requirements-test.php` (in CI) checks that every required check has a field on its template.
- **A4 PDF:** a receipt taller than a page continues on the next pages, cut between rows, instead of shrinking to 40 % width; the canvas scale drops below 2 when the image would pass iOS's ~16.7 MP limit (#34).
- **Picker and form:** values typed into fields both receipt types have (property, Amount Paid, tip…) carry over when switching type (#35); the address bar keeps `?type=<slug>`, so a reload or a shared link opens the same type (#36). Logos are shrunk to 400 px on upload and the form is saved once typing pauses (#40). Bidi control characters are stripped from the receipt and the verify page (#39).
- **Widget:** the auto-height script checks `new URL(site).origin` as a JSON string and finds the iframe by its window, not by a URL selector, and the code shown before the script runs includes it too (#41). The `/embed/` page has favicon links, and the QR code's space is held while its library loads (#44).
- **Profile:** Job Title and Expertise appear once on the profile screen and are saved with `sanitize_text_field( wp_unslash() )` (#42).
- **Small fixes:** tap targets of 44 px for template hub links, the QR checkbox and byline links (#43); wide tables in posts scroll sideways on phones, the header menu wraps, hub cards show focus only for keyboard focus (#44).

## 1.3.2.1 — 20 Oct 2026

- The two shortened guide titles now show live: those posts store their full headline in `rf_seo_title`, so a stored title over 60 characters gives way to the one in `rf_seo_title_patches()`.

## 1.3.2 — 20 Oct 2026 (audit of 20 Oct, Phase 2: technical SEO)

- **Every meta description is 155 characters or less** (13 were 161–205): the state law pages get a short `meta` next to their `summary` (the page text doesn't change), and the requirements checker, widget, rent, HRA, donation, payment and missing receipt form descriptions are shorter.
- **Every title is 60 characters or less.** State pages use "{State} Rent Receipt Law: Is a Receipt Required?" ("DC" for the District of Columbia, was 73 characters), and two guides get a shorter SEO title from `rf_seo_title_patches()`, which `seo-meta.php` now reads when a post has no `rf_seo_title` of its own (no database change).
- **Date archives** (`/2026/`, `/2026/09/`) redirect to `/blog/` (301); date and attachment pages that are still reached are `noindex`. The fallback canonical ends in a slash.
- **"Discourage search engines" works again:** with it on, every page says `noindex, nofollow`. Core's `wp_robots()` is now removed only while the theme prints the SEO tags, so an SEO plugin keeps its robots tag.
- **The `/blog/<slug>/` link fix leaves `/blog/feed/`, `/blog/category/…`, `/blog/tag/…` and `/blog/page/…` alone.**
- **Removed state pages:** a page under `/rent-receipt-laws/` with no law data redirects to the hub, and the page list pass sets such pages to draft.
- **No theme JSON-LD when Yoast or Rank Math is active** (the plugin prints its own).
- **`rf_blog_url()`** gives `/blog/` when the Posts page setting is empty (breadcrumbs, canonical, header menu, homepage button); `get_permalink( 0 )` was the current page.
- **Legal pages:** "Last reviewed: October 2026" on the rent law hub and every state page (`rf_rent_laws_reviewed()`), and a `WebPage` node with `dateModified`, `lastReviewed`, `reviewedBy` and the statutes or official sources as `citation` on those pages and `/receipt-requirements/`. `/blog/` gets BreadcrumbList + CollectionPage.
- `og:locale` follows the site language, `og:image:alt` added; no comments feed link.
- California statute link goes straight to `civil_code_section_1499` (the old URL redirected).
- `tests/php/seo-test.php` (run in CI) checks title and description lengths and the `/blog/` link rewrite.

## 1.3.1 — 20 Oct 2026 (audit of 20 Oct, Phase 1: trust and safety)

- **Verify page no longer overclaims.** The QR code is not signed, so anyone can make one. The result is now a neutral blue note ("These are the details stored in this QR code. Anyone can create a code, so this does not prove who issued the receipt or that it was paid.") instead of a green "as issued". The page text, the QR hint under the generator, the SEO title/description, the FAQ, the templates hub line and `/llms.txt` drop the "easy to spot a changed receipt" claims. All of this text is in the theme, not in page content, so no content patch is needed.
- **Verify page checks the link fully** before showing anything: item lines and extra fields must be lists, amounts whole numbers. The status is set only after the details render, so a damaged link never shows a result and then breaks.
- **`?rf_embed[]=x` no longer gives HTTP 500** on any URL. `rf_embed` only counts on `/embed/<template>/` paths and must be a known template (`rf_embed_slug()`); `/?rf_embed=cash-receipt` is a normal page again, and `frame-ancestors *` is sent only for a valid embed.
- **Receipt numbers no longer repeat.** After a download the field moves on to the next number, and a number saved in the tab that was already downloaded moves on after a reload. A year at the end stays (`A-1/2026` → `A-2/2026`), and long numbers are counted as text, so nothing past 2^53 loses digits.
- **Currency follows the receipt type.** Switching from HRA (INR) to a type without its own currency goes back to the page default (USD) unless you picked another currency yourself; the payment method follows the type only until you choose one.
- **Footer text contrast** fixed (`.rf-site-footer p` was 2.35:1, now #cbd5e1 on the dark footer).
- **Admin "links" fix** keeps backslashes in content (`wp_slash()` in both `wp_update_post()` calls).
- Unit tests for the receipt number rules and the verify link checks.

### Deploy helper (`rgp-deploy-helper.php` 1.1.2)
- Waits while Git is still writing: no copy while `.git/index.lock` exists or until HEAD / the branch ref / the index are 60 seconds old.
- Refuses a run that would delete a whole theme/plugin folder or more than 20% of the files it manages, logs why on Tools → Deploy Helper and retries on the next check. "Deploy now" has an "Allow removing many files" box for intended removals.
- A revision counts as deployed only when every file went through and the checkout did not change during the copy; otherwise it runs again.
- Atomic lock (`INSERT IGNORE` on the `rgp_deploy_lock` option, cleared after 10 minutes if a run died) instead of a transient, unique temp file names, and source = target is refused.

## 1.3.0 — 16 Oct 2026 (receipt type picker and designs)

- **Receipt type picker on the homepage.** "1. Choose your receipt type" lists the general receipt and every template in `rf_receipt_categories()`. Picking one swaps the form in place (labels, placeholders, extra fields, tip / amount paid, statement, signature lines, default payment method, currency and requirement check) and keeps what the types share. No new URLs: each option is a link to that template's own page, so crawlers and visitors without JavaScript land there, and a "guide" link under the picker points to it. The type is kept with the rest of the form for the tab session.
- **Nine receipt designs** over every preview (homepage, template pages, rent law pages, embed widget): Classic, Modern Blue, Minimal, Bold Dark, Formal, Thermal Slip, Compact, Green Stripe and Office + Customer Copy. Downloads use the same design. Thermal Slip exports at receipt-roll width and its PDF is an 80 mm page; Office + Customer Copy prints the receipt twice with a cut line. The last design chosen is remembered in this browser (localStorage).
- `rf_generator_form()` holds the form defaults in one place for the template and the picker; `rf_generator_types()` and `rf_receipt_designs()` are the picker and design registries.
- Unit tests for the type field markup and the copies layout.

## 1.2.0 — 14 Oct 2026 (five new SEO tools)

New pages are real WordPress Pages, created automatically on the first request after deploy (`inc/feature-pages.php`, option `rf_feature_pages_hash`); their text lives in the theme templates.

- **Requirement checker.** Under every generator, "Check this receipt for" an HRA claim, a dependent care FSA / child care credit, a $250+ donation, a business expense, a US rent payment or a missing receipt declaration. Each line ticks as you type, and points to the template that has a missing field. Rules live in `inc/requirements.php`, each traced to an official source; `/receipt-requirements/` prints the same lists with sources. Template pages pick a sensible check by default; `?purpose=` preselects one.
- **Verification QR code.** Receipts carry a QR code (on by default, can be switched off) linking to `/verify-receipt/#r=…`. The details travel after the `#`, so they are never sent to the server; the verify page decodes them in the browser so anyone can spot a receipt changed after it was made. Uses qrcode-generator 1.4.4 from cdnjs with SRI.
- **Missing receipt form.** New template `/missing-receipt-form/` (lost receipt declaration): vendor, date, business purpose, reason, employee declaration and an approver signature. The generator supports `number_label` and `signature2` for it.
- **Rent receipt laws by state.** `/rent-receipt-laws/` and one page per jurisdiction (16 states + D.C.) with the statute, when a receipt is required, required contents, records and penalties, plus the rent generator. Data in `inc/rent-laws.php`, read from each statute's text in October 2026.
- **Embeddable widget.** `/embed/<template>/` serves the generator alone (no ads, consent or plugins, `noindex`, `frame-ancestors *` instead of `X-Frame-Options`). `/receipt-generator-widget/` builds the embed code: iframe, a credit link, and an optional auto-height script.
- Templates hub, footer and `/llms.txt` link the new tools; the hub description counts templates instead of saying 16.
- Unit tests for the checks, the QR payload round trip and the embed code (`tests/v120-tools.test.js`).

## 1.1.7 — 14 Oct 2026 (site audit, Phase 5: content goes live)

- One-time migration on the first request after deploy (approved by the owner): applies the Phase 5 page text (rent/HRA cross-links, About "What We Never Store", Contact "What to Include in Your Email") and the three shorter SEO titles, then purges LiteSpeed. Pages are changed with `wp_update_post()`, so each keeps a revision. Runs once (option `rf_migration_phase5`, which also stores a log).
- `rf_fixes_apply_patch()` moved to `inc/content-patches.php` so the migration can use it outside wp-admin.

## 1.1.6 — 14 Oct 2026 (site audit, Phase 5: generator extras)

### Generator
- New "Discount and a second tax" section (collapsed by default): a discount as a percentage or a fixed amount, taken off the subtotal before tax; a name for the first tax; and an optional second tax with its own name and rate (for example CGST 9% + SGST 9%). Each prints on its own line.
- The receipt number continues from the last one downloaded in this browser (1001 → 1002, INV-0099 → INV-0100). Only that number is kept, in localStorage; nothing is uploaded.
- Unit tests cover the new totals and numbering.

## 1.1.5 — 14 Oct 2026 (site audit, Phase 5: content and housekeeping)

### Content (Tools → ReceiptGenPro Fixes)
- Section 2 now also adds a "who this page is for" sentence to the first paragraph of /rent-receipt/ and /hra-rent-receipt/ that links each to the other, a "What We Never Store" section to About Us, and a "What to Include in Your Email" section with the time zone to Contact Us. Each runs only once.
- New section 6 sets shorter SEO titles (60 characters or less) on three guides: how long to keep receipts, digital vs paper, and receipt template formats.
- The patch tool supports `unless` (skip when text is already there) and `backrefs` (keep `$1` in regex replacements).

### Theme
- The freelance template prints "FREELANCE PAYMENT RECEIPT", so it no longer looks the same as /payment-receipt/.
- Removed the unused "Blog Sidebar" widget area and the dead `author.php` (author URLs already redirect to About Us).
- `Tested up to: 7.1` (the live site runs WordPress 7.1.3).

### CI
- New GitHub Action: `php -l` on every PHP file, `node --check` on the theme JS, and Node unit tests for the money and amount-in-words helpers (`tests/receipt-generator.test.js`).

## 1.1.4 — 14 Oct 2026 (site audit, Phase 4: performance, accessibility, hardening)

### Accessibility
- Grey meta text (post meta, blog card meta, author label, receipt footer and empty hint) changed from `#94a3b8` to `#64748b` (4.76:1 on white).
- Form inputs, selects, textareas, item rows and the currency picker get a darker border (`#8492a6`, 3.16:1); cards keep the light border.
- The receipt preview is no longer an `aria-live` region (it was read out in full on every keystroke). A hidden status line announces only "Total: …" once typing pauses.
- Links inside post, page, template, FAQ and archive text are underlined.
- Visible `:focus-visible` outline on links, buttons, inputs and `.rf-btn`.
- Receipt Templates hub cards: only the title is a link, stretched over the card, so screen readers no longer read the whole card as one link.

### Performance
- Author box and author page no longer load Gravatar. They show the photo from the new profile field "Author photo URL", or the author's initials.
- Removed the `WP_POST_REVISIONS` define from the theme (core defines it first, so it did nothing). Put `define( 'WP_POST_REVISIONS', 5 );` in `wp-config.php` if wanted.

### Security
- The `Link: …?p=123` shortlink HTTP header is removed.
- Top-level templates exit when requested directly instead of returning HTTP 500.

### Deploy helper (`rgp-deploy-helper.php` 1.1.1)
- The webhook also accepts the key in an `X-RGP-Key` header. `?key=` keeps working.

## 1.1.3 — 14 Oct 2026 (site audit, Phase 3: functionality and UX)

After the update goes live: **LiteSpeed Cache → Purge All** (from this release on, the deploy helper purges it automatically after each deploy).

### Generator (`assets/js/receipt-generator.js`, `template-parts/generator-embed.php`)
- INR and PKR amounts in words use lakh and crore (1,20,000 reads "One lakh twenty thousand Indian rupees only").
- The form keeps its entries (fields, item rows, logo) across a reload until the tab is closed, using sessionStorage in the browser only. This makes the hub FAQ's claim true.
- PNG, JPEG and PDF exports render an off-screen copy at 800 px (1600 px at scale 2), so receipts made on a phone are as sharp as on a laptop.
- "Change / Credit" is shown instead of "Balance Due" when the amount paid exceeds the total.
- New "Remove logo" button.
- A failed export shows its own message instead of "library could not be loaded".
- Downloads are named `receipt-<number>.<ext>`.

### Theme
- Footer fallback menus and the 404 page list every template from `rf_receipt_categories()` (HRA, Payment and Deposit were missing).
- Mechanic template: new "Warranty / Guarantee" field; hotel and mechanic highlights reworded to match what the form does.
- CSS and JS are versioned by file modification time, so a changed file is never served stale.

### Deploy helper (`rgp-deploy-helper.php` 1.1.0)
- `functions.php` files are copied last, so a new `inc/` file is in place before the code that loads it.
- Purges the LiteSpeed page cache after a deploy.
- Markdown files and dotfiles are no longer published (this CHANGELOG was publicly readable); the copy already on the live site is removed on the next deploy.

## 1.1.2 — 14 Oct 2026 (site audit, Phase 1)

After the update goes live: **LiteSpeed Cache → Purge All** so cached pages pick up the new files.

### Generator (`assets/js/receipt-generator.js`)
- Default date is the visitor's local date (was UTC, so evenings in the Americas showed tomorrow).
- Amounts are rounded to whole cents before they are added up, so Subtotal + Tax + Tip always equals the printed Total (10.25 at 18% showed 10.25 + 1.84 = 12.10).
- Amount in words no longer says "one hundred cents" (3.00 read "Two US dollars and one hundred cents"); "trillion" added.
- Negative prices, quantities, tax rate, tip and amount paid count as 0.
- html2canvas and jsPDF are loaded from cdnjs with Subresource Integrity hashes.

### Theme
- Mobile menu: the fallback menu (used while no Primary menu is assigned) now prints the vertical mobile list instead of the desktop row, so it no longer runs off the side of a phone. The menu also closes on Escape or when a link is tapped.
- Blog and archive cards show each post's date (the_date() printed it only on the first card of a day).

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
