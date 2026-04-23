# Plan: Magento 2 module for Site Search AI

This document captures the implementation plan for a Magento 2 extension that mirrors the [WordPress plugin](https://github.com/Springbok-Agency/ai-search-wordpress-plugin) and [Drupal module](https://github.com/Springbok-Agency/ai-search-drupal-plugin) (same browser contract: `window.SSAI_CONFIG`, `search-widget.js`, `POST {search}/answer_wordpress` with `X-Customer-ID`).

## 0. Current state and constraints

- This repo is the home for the Magento module (initially empty except Git).
- Reference implementations:
  - WordPress: `site_search_ai.php`, `includes/class-frontend.php`, `includes/class-admin-settings.php`, `assets/js/search-widget.js`, `assets/css/search-widget.css`.
  - Drupal: `site_search_ai` module (Firestore sync, `SiteSearchAiConfigBuilder`, blocks, overview inject).
- Backend endpoints (server-side; same as WP):
  - `GET {SSAI_BACKEND_URL}/config/get?config_code=…` or `widget_code=…` → Firestore-backed config.
  - `GET {SSAI_BACKEND_URL}/namespaces` with header `X-Customer-ID`.
  - `POST {SSAI_BACKEND_URL}/load_rag` (multipart: `sitemap`, `pdfs`, `max_links`, `namespace`, `auto_generate_namespace`) + `X-Customer-ID`.
  - `POST {SSAI_BACKEND_URL}/load_rag/product_feed` (multipart `product_feed` JSON) + `X-Customer-ID`.
- Production defaults (overridable; same idea as WP `SSAI_*_DEFAULT`):
  - Customer management backend: `https://customer-management-backend-jkwzc77jbq-ez.a.run.app`
  - Search API base: `https://search.oryonx.nl`

## 1. Target and packaging

1. **Magento 2 only** (not Magento 1). Target **Magento 2.4.6+**, **PHP 8.2+**.
2. **Module identity:** `Springbok_SiteSearchAi` under namespace `Springbok\SiteSearchAi`. Composer package name: `springbok/module-site-search-ai`.
3. **Repo layout:** Composer-ready module at repo root (not nested under `app/code/` in Git); README documents manual `app/code` copy vs Composer install.
4. **License:** Align with WP/Drupal (GPL-2.0-or-later is used by sibling plugins; confirm vs Magento Marketplace OSL-3.0 if publishing there).

## 2. Proposed directory layout

```
composer.json
registration.php
etc/
  module.xml
  di.xml
  acl.xml
  config.xml
  cache.xml                    # optional dedicated cache type for Firestore
  adminhtml/
    routes.xml
    system.xml
    menu.xml                   # optional top-level admin menu
  frontend/
    routes.xml                 # only if a frontend controller is needed
    page_types.xml
Block/
  Frontend/ (Config, SearchBox, Overview)
Controller/Adminhtml/
  Config/Sync.php
  Namespaces/Fetch.php
  Rag/Upload.php
  ProductFeed/Upload.php
  ProductFeed/Generate.php     # Magento-native catalog → JSON → upload
Model/
  Config.php
  ResolvedUrls.php
  CustomerIdProvider.php
  FirestoreMapper.php          # port map_firestore_to_wordpress / Drupal mapper
  ConfigResolver.php             # GET /config/get + 300s cache
  RagClient.php
  ProductFeed/Builder.php
ViewModel/WidgetConfig.php
Setup/Patch/Data/              # optional: seed customer_id
view/
  adminhtml/
    layout, templates, web/css, web/js
  frontend/
    layout/default.xml
    layout/catalogsearch_result_index.xml
    templates (config, search_box, overview)
    web/css/search-widget.css  # copy from WP
    web/js/search-widget.js    # copy from WP
    web/js/overview-inject.js  # Drupal overview-inject parity
i18n/en_US.csv, nl_NL.csv
README.md
LICENSE
```

## 3. Admin configuration (`system.xml`)

Mirror WP `ssai_settings` groupings; store under paths like `ssai/sync/…`, `ssai/styling/…`. Use **website** scope where multi-store matters (`customer_id`, `namespace`).

- **Sync:** `config_code`, `widget_code`.
- **Customer / RAG:** `customer_id` (readonly; generated on install), `namespace` override.
- **Selectors:** `search_selector` as JSON textarea (validate like Drupal `search_selectors_json`), `results_page_selector` textarea.
- **Overview:** `auto_inject_overview`, `overview_target_selector`.
- **Behavior:** `live_search`, `position` (`below` / `above`).
- **Styling:** `primary_color`, `result_*`, `show_ai_emoji`, `ai_emoji_char`, `powered_by_prominence`.
- **Feedback:** optional `feedback_url`.

**Precedence:** When sync codes are set, merge Firestore response over stored config (cached 300s). **Search and backend base URLs** are never taken from Firestore only—use env/deployment overrides plus safe defaults (see §8).

## 4. Custom admin page: RAG Data Management

`system.xml` alone is not enough for uploads. Add a dedicated admin route (e.g. `admin/ssai/settings/index`) with tabs matching WP admin:

- Fetch namespaces → `GET /namespaces`.
- Upload sitemap/PDFs → `POST /load_rag` (multipart; same field names as WP, including `pdfs` not `pdfs[]`, `auto_generate_namespace`).
- Upload product feed JSON → `POST /load_rag/product_feed`.
- Manual config sync → `GET /config/get`, refresh cache and optional config fields.

**Magento-specific:** `ProductFeed/Generate` builds JSON from catalog and calls upload (see §6).

Use Magento ACL, form keys, and appropriate timeouts (15s config, 60s namespaces, 300s RAG). Port WP `admin.js` / `admin.css` with `requirejs` and `jquery`.

## 5. Frontend integration

1. **`default.xml`:** Inject block before `</body>` that outputs `window.SSAI_CONFIG` with the same shape as `SSAI_Frontend::render_config()` in WordPress (`apiUrl` = `{searchBase}/answer_wordpress`, `customerId`, `namespace`, `searchSelector`, `styling`, `isLoggedIn`, `feedbackUrl`, `i18n`, etc.).
2. **Assets:** Ship `search-widget.css` and `search-widget.js` copied from the WordPress plugin; bump version when assets change (cache busting).
3. **`catalogsearch_result_index.xml`:** Place overview block above results; read query via Magento catalog search APIs (`q` param is already supported by the widget).
4. **Auto-inject:** When `auto_inject_overview` or `overview_target_selector` is set, load `overview-inject.js` (parity with WP `inject_ai_overview_script` / Drupal `overview-inject.js`).
5. **Search box:** Provide layout/widget or CMS integration analogous to `[site_search_ai]` shortcode (same HTML structure: `.ssai-search-container`, etc.).

## 6. Magento-native product feed (bonus)

WordPress expects a manual JSON upload; Magento can generate it:

- Iterate enabled, visible products in batches; map to the JSON shape expected by `load_rag/product_feed` (align with `ai-search-rag-management` expectations).
- Triggers: admin “Generate & upload”, optional daily cron, optional incremental events (phase 2).

## 7. Customer ID and activation

- On install/patch: if `customer_id` empty, generate `cust_` + 12 alphanumeric chars and persist in config (parity with WP `ssai_activate()`).
- Optional “Regenerate” behind strict ACL.

## 8. URL resolution

Priority:

1. Deployment config (e.g. `env.php` / constants `SSAI_BACKEND_URL`, `SSAI_SEARCH_URL` if you standardize on WP naming).
2. Optional hidden system config for non-default hosts.
3. Hard-coded production defaults in code.

Validate search base is absolute `http(s)://…` before emitting into `window.SSAI_CONFIG` (same guard as Drupal `isValidApiBaseForWidget`).

## 9. Reuse unchanged from WordPress

- `search-widget.css` and `search-widget.js` (keep in sync; single source of truth ideally).
- `window.SSAI_CONFIG` contract.
- Firestore mapping logic (port from WP `map_firestore_to_wordpress` or Drupal `SiteSearchAiFirestoreMapper`).

## 10. Magento-specific (do not mirror WP literally)

- Config: `core_config_data` + `system.xml`.
- Cache: `CacheInterface` or dedicated cache type; flush from Admin.
- Admin: ACL + form keys (not WP nonces).
- HTTP: Guzzle or Magento HTTP client with documented timeouts.
- i18n: CSV + `__()` / `$t()`.
- Multi-store: website-scoped customer ID and namespace where needed.

## 11. Compatibility with Magento search

- Document interaction with native search, Algolia, ElasticSuite; ship sensible default selectors.
- Optional “replace native catalog search results” mode (off by default): layout removes default product list on search results when enabled.

## 12. Testing

- PHPUnit: `FirestoreMapper`, `WidgetConfig`, `CustomerIdProvider`, `ResolvedUrls`.
- Integration: admin controllers with mocked HTTP; assert multipart matches WP behavior.
- PHPCS Magento2, PHPStan in CI.
- Optional Docker smoke: homepage contains valid `window.SSAI_CONFIG`.

## 13. Security

- Escape JSON in inline script (`escapeJs` or safe encoding).
- Validate/sanitize styling fields where inlined.
- Dedicated ACL resource for RAG uploads vs full config.
- Form key on admin POSTs.

## 14. Versioning and release

- Tag module semver; document mapping to widget JS version (WP currently `SSAI_VERSION` / library bumps).
- CI: tag → Packagist; Marketplace is a separate track.

## 15. Suggested build order

1. Skeleton: `composer.json`, `registration.php`, `module.xml`, clean `setup:upgrade`.
2. Read-only widget: static `SSAI_CONFIG` + copied JS/CSS; verify against staging backend.
3. `system.xml` + config reader + `WidgetConfig`.
4. `CustomerIdProvider` + install patch.
5. `ResolvedUrls` + `ConfigResolver` + `FirestoreMapper` + cache.
6. Admin RAG page + controllers (namespaces, upload, sync).
7. Overview block + search results layout + overview inject.
8. Search box widget / layout placement.
9. Native product feed generator.
10. CI, README, release.

## Open questions (resolve when picking up)

1. Vendor name for Packagist/Marketplace: `Springbok` vs other?
2. Minimum Magento: confirm **2.4.6+ / PHP 8.2+** only?
3. Cross-repo versioning: lockstep `search-widget.js` across WP/Drupal/Magento or allow drift?
4. Any separate “Magento integration” doc (e.g. Page Builder, GraphQL) to fold into §5/§11?
