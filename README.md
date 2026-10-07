# Italgres 3D Furniture Configurator — demo store

Magento Open Source 2.4.9 + Hyvä 1.5 (standard Composer project) that shows **only** 3D configurator product
pages: guided option steps, a live Three.js model that follows every choice, live price and dimensions, a
shareable configuration link and a **Request a quote** flow (saved in *Sales › Quote Requests*, emailed to sales).
Every other storefront URL redirects to the landing product, so the demo can run on its own subdomain.

## What is in this repository

| Path | What |
|---|---|
| `app/code/Italgres/Configurator` | Reusable configurator module: quote requests, materials and 3D model libraries, 3D columns on custom options, storefront template and viewer, rule validation |
| `app/code/Italgres/DemoMode` | Demo-only: storefront lock, header/footer, `italgres:demo:install`, fixtures (models, textures, swatches) and asset generators |
| `app/design/frontend/Italgres/configurator` | Hyvä child theme (plain CSS, `ig-` prefix; no Tailwind build needed) |
| `app/etc/config.php` | Enabled modules (`Magento_TwoFactorAuth` and `Magento_AdminAdobeImsTwoFactorAuth` are off) |
| `composer.json` / `composer.lock` | Magento core from repo.magento.com, Hyvä from Hyvä's public GitHub repositories |
| `nginx.conf` | `nginx.conf.sample` with `fastcgi_pass $fastcgi_backend;` |

Everything else (Magento core, Hyvä, `vendor/`, `bin/`, `pub/`, `generated/`, `var/`) comes back with
`composer install`; `app/etc/env.php` and `pub/media` are per environment. See `.gitignore` (a whitelist).

Hyvä's Mollie bundle is excluded through `replace` in `composer.json` (Mollie is not used).

## Installing

Requirements: PHP 8.3/8.4, MariaDB 11.4 or MySQL 8.4, OpenSearch 2/3, Redis (recommended), Composer 2,
nginx, about 4 GB RAM, HTTPS.

1. Clone, copy `auth.json.sample` to `auth.json` with your Magento Marketplace keys, then
   `composer install --no-dev` (drop `--no-dev` on a development machine).
2. `bin/magento setup:install …` with the target base URL and a non-default `--backend-frontname`
   (or import a database dump and update `web/secure/base_url` / `web/unsecure/base_url`).
3. `bin/magento italgres:demo:install` — copies the demo assets to `pub/media`, creates the materials, 3D models
   and the configurator products, and sets the theme and demo configuration. Re-runnable; it overwrites admin
   edits to the demo products.
4. `bin/magento indexer:set-mode realtime` (or set up cron), then `bin/magento cache:flush`.
5. Production: `bin/magento deploy:mode:set production`. Use a strong admin password and consider HTTP basic auth
   while it is a preview; the installer already sets robots to `NOINDEX,NOFOLLOW`.

Email: set *Stores › Configuration › Advanced › System › Mail Sending Settings* to SMTP for the real host, and the
sales recipients under *Stores › Configuration › Italgres › 3D Configurator*.

## 3D viewer bundle

Source: `app/code/Italgres/Configurator/frontend-src` (esbuild + three.js).
`npm install && npm run build` writes `view/frontend/web/js/viewer.js`, which is committed.
It also copies three.js's Draco decoder to `view/frontend/web/js/draco/`. New `.glb` models should be WebP + Draco
compressed as described in `frontend-src/README.md` (about 80% smaller than raw exports).

## Asset licences

- Sofa and lounge chair models: © 2021 Wayfair LLC, **CC BY 4.0** (Khronos glTF Sample Assets), compressed for
  the web (WebP textures, Draco geometry). The credit is shown under the viewer and must stay while they are used.
- Coffee table model, modular sofa elements and porcelain slab textures: generated for this demo
  (`app/code/Italgres/DemoMode/tools/`).
- Fabric, leather and wood textures: Poly Haven, CC0.
- Fonts: Cormorant Garamond and Jost, SIL OFL 1.1 (`Configurator/view/frontend/web/fonts/`).
- Draco decoder (from three.js): Apache 2.0 (`Configurator/view/frontend/web/js/draco/`).

These are stand-ins until the client supplies their own 3D models and material scans.
