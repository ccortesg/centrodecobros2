const path = require('path');
const webpack = require('webpack');

async function loadConfig(mode) {
    process.env.NODE_ENV = mode;
    process.env.MIX_FILE = 'webpack.mix.js';

    const configFactory = require(path.resolve(
        __dirname,
        '..',
        '..',
        'node_modules',
        'laravel-mix',
        'setup',
        'webpack.config.js'
    ));

    return await configFactory();
}

function printError(error) {
    if (!error) {
        return;
    }

    if (error.stack) {
        console.error(error.stack);
    } else {
        console.error(error);
    }

    if (error.details) {
        console.error(error.details);
    }
}

async function main() {
    const mode = process.argv[2] === 'production' ? 'production' : 'development';
    const config = await loadConfig(mode);
    const compiler = webpack(config);

    compiler.run((error, stats) => {
        const hasErrors = error || (stats && stats.hasErrors());
        const exitCode = hasErrors ? 1 : 0;

        if (error) {
            printError(error);
        }

        if (stats && stats.hasErrors()) {
            console.error(stats.toString({ preset: 'errors-only', colors: true }));
        }

        compiler.close(closeError => {
            if (closeError) {
                printError(closeError);
                process.exit(1);
            }

            process.exit(exitCode);
        });
    });
}

main().catch(error => {
    printError(error);
    process.exit(1);
});
