const fs = require('fs');
const path = require('path');

let transform;

try {
    ({ transform } = require('esbuild'));
} catch (error) {
    throw new Error(
        'Missing direct dependency "esbuild" required by scripts/local/build_legacy_lane.js. ' +
        'Run npm install so the legacy lane does not depend on Vite transitive hoisting.'
    );
}

const mode = process.argv[2] === 'production' ? 'production' : 'development';
const projectRoot = path.resolve(__dirname, '..', '..');

const legacyCssSources = [
    'resources/assets/plantilla/css/font-awesome.min.css',
    'resources/assets/plantilla/css/simple-line-icons.min.css',
    'resources/assets/plantilla/css/style.css',
];

const legacyJsSources = [
    'resources/assets/plantilla/js/jquery.min.js',
    'resources/assets/plantilla/js/popper.min.js',
    'resources/assets/plantilla/js/bootstrap.min.js',
    'resources/assets/plantilla/js/Chart.min.js',
    'resources/assets/plantilla/js/pace.min.js',
    'resources/assets/plantilla/js/template.js',
    'resources/assets/plantilla/js/template.shared.js',
    'resources/assets/plantilla/js/template.ajax-hash.js',
    'resources/assets/plantilla/js/sweetalert2.all.js',
];

function resolveProjectPath(relativePath) {
    return path.join(projectRoot, relativePath);
}

function readSource(relativePath) {
    const absolutePath = resolveProjectPath(relativePath);

    if (!fs.existsSync(absolutePath)) {
        throw new Error(`Legacy source not found: ${relativePath}`);
    }

    const content = fs.readFileSync(absolutePath, 'utf8').trim();

    if (!content.length) {
        throw new Error(`Legacy source is empty: ${relativePath}`);
    }

    return content;
}

function annotateSource(relativePath, content, type) {
    if (mode === 'production') {
        return content;
    }

    const banner = type === 'css'
        ? `/* source: ${relativePath} */\n`
        : `/* source: ${relativePath} */\n`;

    return `${banner}${content}`;
}

function readSources(sources, separator) {
    return sources
        .map(source => annotateSource(source, readSource(source), separator === '\n' ? 'css' : 'js'))
        .join(separator)
        .concat('\n');
}

async function maybeMinify(content, loader) {
    if (mode !== 'production') {
        return content;
    }

    const result = await transform(content, {
        charset: 'utf8',
        legalComments: 'none',
        loader,
        minify: true,
    });

    return result.code.endsWith('\n') ? result.code : `${result.code}\n`;
}

async function writeOutput(relativeTarget, content) {
    const targetPath = resolveProjectPath(relativeTarget);

    fs.mkdirSync(path.dirname(targetPath), { recursive: true });
    fs.writeFileSync(targetPath, content, 'utf8');

    const stats = fs.statSync(targetPath);

    if (stats.size === 0) {
        throw new Error(`Legacy output is empty: ${relativeTarget}`);
    }

    console.log(`legacy:${mode}: ${relativeTarget} (${stats.size} bytes)`);
}

async function main() {
    const cssBundle = await maybeMinify(readSources(legacyCssSources, '\n'), 'css');
    const jsBundle = await maybeMinify(readSources(legacyJsSources, '\n;\n'), 'js');

    console.log(`legacy:${mode}: css order -> ${legacyCssSources.join(' -> ')}`);
    console.log(`legacy:${mode}: js order -> ${legacyJsSources.join(' -> ')}`);

    await writeOutput('public/css/plantilla.css', cssBundle);
    await writeOutput('public/js/plantilla.js', jsBundle);
}

main().catch(error => {
    console.error(error);
    process.exit(1);
});
