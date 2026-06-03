const fs = require('fs');
const path = require('path');

const projectRoot = path.resolve(__dirname, '..', '..');
const buildRoot = path.join(projectRoot, 'public', 'build');
const manifestPath = path.join(projectRoot, 'public', 'build', 'manifest.json');
const bridgePath = path.join(projectRoot, 'public', 'js', 'app.js');
const entryKey = 'resources/assets/js/app.js';

function collectCssAssets(manifest, key, collected = new Set(), visited = new Set()) {
    if (visited.has(key) || !manifest[key]) {
        return collected;
    }

    visited.add(key);

    for (const cssFile of manifest[key].css || []) {
        collected.add(`/build/${cssFile}`);
    }

    for (const importedKey of manifest[key].imports || []) {
        collectCssAssets(manifest, importedKey, collected, visited);
    }

    return collected;
}

function createBridgeSource(jsAsset, cssAssets) {
    return `/* Phase 16 Vite bridge for the stable public/js/app.js contract. */
(function () {
    var moduleSrc = ${JSON.stringify(jsAsset)};
    var cssAssets = ${JSON.stringify(cssAssets, null, 4)};
    var currentScript = document.currentScript || document.querySelector('script[src$="/js/app.js"], script[src="js/app.js"], script[src$="js/app.js"]');
    var head = document.head || document.getElementsByTagName('head')[0];

    function resolveAssetUrl(assetPath) {
        if (/^(?:[a-z]+:)?\\/\\//i.test(assetPath) || assetPath.charAt(0) === '/') {
            return assetPath;
        }

        if (currentScript && currentScript.src) {
            return new URL(assetPath, currentScript.src).toString();
        }

        return new URL(assetPath, document.baseURI || window.location.href).toString();
    }

    cssAssets.forEach(function (href) {
        var resolvedHref = resolveAssetUrl(href);

        if (document.querySelector('link[data-phase16-vite-css="' + resolvedHref + '"]')) {
            return;
        }

        var link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = resolvedHref;
        link.setAttribute('data-phase16-vite-css', resolvedHref);
        head.appendChild(link);
    });

    var resolvedModuleSrc = resolveAssetUrl(moduleSrc);

    if (document.querySelector('script[data-phase16-vite-entry="' + resolvedModuleSrc + '"]')) {
        return;
    }

    var script = document.createElement('script');
    script.type = 'module';
    script.src = resolvedModuleSrc;
    script.setAttribute('data-phase16-vite-entry', resolvedModuleSrc);

    if (currentScript && currentScript.parentNode) {
        currentScript.parentNode.insertBefore(script, currentScript.nextSibling);
        return;
    }

    (document.body || document.documentElement).appendChild(script);
})();
`;
}

function readManifest() {
    try {
        return JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
    } catch (error) {
        throw new Error(`Unable to parse Vite manifest at ${manifestPath}: ${error.message}`);
    }
}

function ensureBuildAssetExists(relativeFile, assetType) {
    const normalizedRelativeFile = relativeFile.replace(/\//g, path.sep);
    const assetPath = path.join(buildRoot, normalizedRelativeFile);

    if (!fs.existsSync(assetPath)) {
        throw new Error(`${assetType} asset "${relativeFile}" not found at ${assetPath}`);
    }
}

function main() {
    if (!fs.existsSync(manifestPath)) {
        throw new Error(`Vite manifest not found at ${manifestPath}`);
    }

    const manifest = readManifest();
    const entry = manifest[entryKey];

    if (!entry || !entry.file) {
        throw new Error(`Entry "${entryKey}" not found in ${manifestPath}`);
    }

    ensureBuildAssetExists(entry.file, 'JS');

    const cssAssets = Array.from(collectCssAssets(manifest, entryKey));

    cssAssets.forEach(cssAsset => {
        ensureBuildAssetExists(cssAsset.replace('/build/', ''), 'CSS');
    });

    const jsAsset = `../build/${entry.file}`;
    const relativeCssAssets = cssAssets.map(cssAsset => `..${cssAsset}`);
    const bridgeSource = createBridgeSource(jsAsset, relativeCssAssets);

    fs.mkdirSync(path.dirname(bridgePath), { recursive: true });
    fs.writeFileSync(bridgePath, bridgeSource, 'utf8');

    console.log(`bridge: public/js/app.js -> ${jsAsset} (${relativeCssAssets.length} css assets)`);
}

main();
