const path = require('path');
const fs = require('fs');
const { spawnSync } = require('child_process');

const mode = process.argv[2] === 'production' ? 'production' : 'development';
const projectRoot = path.resolve(__dirname, '..', '..');
const nodeExecutable = process.execPath;
const viteExecutable = path.join(projectRoot, 'node_modules', 'vite', 'bin', 'vite.js');
const requiredOutputs = [
    'public/css/plantilla.css',
    'public/js/plantilla.js',
    'public/js/guest-public.js',
    'public/build/manifest.json',
    'public/js/app.js',
];

function runStep(label, command, args) {
    console.log(`hybrid-build:${mode}: ${label}`);

    const result = spawnSync(command, args, {
        cwd: projectRoot,
        env: {
            ...process.env,
            NODE_ENV: mode,
        },
        stdio: 'inherit',
    });

    if (result.status !== 0) {
        process.exit(result.status || 1);
    }
}

function ensureFileExists(relativePath) {
    const absolutePath = path.join(projectRoot, relativePath);

    if (!fs.existsSync(absolutePath)) {
        throw new Error(`Expected build artifact not found: ${relativePath}`);
    }

    const stats = fs.statSync(absolutePath);

    if (stats.size === 0) {
        throw new Error(`Expected build artifact is empty: ${relativePath}`);
    }

    console.log(`hybrid-build:${mode}: verified ${relativePath} (${stats.size} bytes)`);
}

function viteBuildArgs() {
    if (mode === 'production') {
        return [viteExecutable, 'build'];
    }

    return [viteExecutable, 'build', '--mode', 'development'];
}

function main() {
    if (!fs.existsSync(viteExecutable)) {
        throw new Error(`Vite executable not found at ${viteExecutable}. Run npm ci before building.`);
    }

    runStep('legacy lane', nodeExecutable, [path.join(projectRoot, 'scripts', 'local', 'build_legacy_lane.js'), mode]);
    runStep('guest public lane', nodeExecutable, [path.join(projectRoot, 'scripts', 'local', 'build_guest_public_lane.js'), mode]);
    runStep('vite app build', nodeExecutable, viteBuildArgs());
    runStep('public/js/app.js bridge', nodeExecutable, [path.join(projectRoot, 'scripts', 'local', 'build_vite_bridge.js'), mode]);

    requiredOutputs.forEach(ensureFileExists);
}

main();
