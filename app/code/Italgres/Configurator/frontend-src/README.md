# Configurator viewer source

`viewer.js` is the Three.js scene used by the storefront configurator. It is bundled (Three.js
included, tree-shaken and minified) into `../view/frontend/web/js/viewer.js`, which the
`configurator.phtml` template loads as an ES module.

```bash
npm install
npm run build   # or: npm run watch while developing
```

Commit the built file; Magento serves it like any other static asset.
