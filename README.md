# Site Search AI — Magento 2 module

Composer package: `springbok/module-site-search-ai`  
Module name: `Springbok_SiteSearchAi`

## Requirements

- Magento **2.4.6+**
- PHP **8.2+**
- `Magento_Csp` (for `etc/csp_whitelist.xml` merge; core in 2.4.x)

## Install

### Composer (path repository)

In the Magento project `composer.json`:

```json
"repositories": [
  {
    "type": "path",
    "url": "../ai-search-magento",
    "options": { "symlink": true }
  }
],
"require": {
  "springbok/module-site-search-ai": "@dev"
}
```

test voor git

Then:

```bash
composer require springbok/module-site-search-ai:@dev
bin/magento module:enable Springbok_SiteSearchAi
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento setup:static-content:deploy -f
bin/magento cache:flush
```

### Manual (`app/code`)

Copy this package under `app/code/Springbok/SiteSearchAi`, then run the same `bin/magento` steps (adjust autoload if needed).

## Configure

1. In the **Site Search AI** dashboard, obtain **Configuration code** and **Widget code**.
2. Magento Admin: **Stores → Configuration → Springbok → Site Search AI**.
3. Paste codes under **Configuration sync**. Save.
4. RAG / knowledge uploads are done in the dashboard, not in Magento.

Optional overrides:

- **Service URLs**: only if not using production defaults or env vars.
- Env on the server: `SSAI_BACKEND_URL`, `SSAI_SEARCH_URL` (override config and defaults).
- **AI overview → Overview block max width** / **Product card max width**: tune how large the overview panel and product images appear on search results (CSS lengths, e.g. `720px` and `120px`).

### Cache

Saving SSAI configuration in the admin clears the remote config cache tag for this module, so you do not need to flush the entire Magento cache when changing SSAI settings.

## Frontend

- Injects `window.SSAI_CONFIG` and loads `search-widget.js` / `search-widget.css` when sync codes are set (same browser contract as the WordPress plugin).
- Does **not** add a separate search field: configure **Search selectors (JSON)** (or Firestore `searchSelectors`) so the widget enhances your theme’s existing inputs (e.g. Luma `#search`).

## Tests (standalone)

From this repo, with dev dependencies installed:

```bash
composer install
./vendor/bin/phpunit -c phpunit.xml.dist
```

Magento integration tests are not included in this repository.

## License

GPL-2.0-or-later (see `LICENSE`).
