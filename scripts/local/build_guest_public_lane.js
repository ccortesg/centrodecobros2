const fs = require('fs');
const path = require('path');

let transform;

try {
    ({ transform } = require('esbuild'));
} catch (error) {
    throw new Error(
        'Missing direct dependency "esbuild" required by scripts/local/build_guest_public_lane.js. ' +
        'Run npm install so the guest public lane build remains reproducible.'
    );
}

const mode = process.argv[2] === 'production' ? 'production' : 'development';
const projectRoot = path.resolve(__dirname, '..', '..');
const sourcePath = path.join(projectRoot, 'resources', 'assets', 'js', 'guest-public.js');
const outputPath = path.join(projectRoot, 'public', 'js', 'guest-public.js');

function readSource() {
    if (!fs.existsSync(sourcePath)) {
        throw new Error(`Guest public source not found at ${sourcePath}`);
    }

    const content = fs.readFileSync(sourcePath, 'utf8').trim();

    if (!content.length) {
        throw new Error(`Guest public source is empty: ${sourcePath}`);
    }

    return content;
}

async function buildSource(content) {
    if (mode !== 'production') {
        return `/* source: resources/assets/js/guest-public.js */\n${content}\n`;
    }

    const result = await transform(content, {
        charset: 'utf8',
        legalComments: 'none',
        loader: 'js',
        minify: true,
    });

    return result.code.endsWith('\n') ? result.code : `${result.code}\n`;
}

async function main() {
    const output = await buildSource(readSource());

    fs.mkdirSync(path.dirname(outputPath), { recursive: true });
    fs.writeFileSync(outputPath, output, 'utf8');

    const stats = fs.statSync(outputPath);

    if (stats.size === 0) {
        throw new Error(`Guest public output is empty: ${outputPath}`);
    }

    console.log(`guest-public:${mode}: public/js/guest-public.js (${stats.size} bytes)`);
}

main().catch(error => {
    console.error(error);
    process.exit(1);
});
