# Configurator viewer source

`viewer.js` is the Three.js scene used by the storefront configurator. It is bundled (Three.js
included, tree-shaken and minified) into `../view/frontend/web/js/viewer.js`, which the
`configurator.phtml` template loads as an ES module.

```bash
npm install
npm run build   # or: npm run watch while developing
```

Commit the built file; Magento serves it like any other static asset.

`npm run build` also copies three.js's Draco decoder (`draco_decoder.wasm` + `draco_wasm_wrapper.js`,
Apache 2.0) to `../view/frontend/web/js/draco/`; the viewer loads it from there, next to the bundle.

## Preparing models

Ship `.glb` files with WebP textures and Draco geometry (about 80% smaller than raw PNG/float exports):

```bash
npx @gltf-transform/cli webp in.glb tmp.glb --quality 90
npx @gltf-transform/cli draco tmp.glb out.glb
```

Avoid `optimize`, `join`, `flatten`, `palette`, `simplify` and quantization (`meshopt`): the configurator
targets meshes and materials by name, and the stretch action rewrites float vertex positions.

