import { defineConfig } from 'vite';
import autoprefixer from 'autoprefixer';
import { browserslistToTargets } from 'lightningcss';
import browserslist from 'browserslist';

export default defineConfig({
    build: {
        outDir: 'dist',
        emptyOutDir: false,
        rollupOptions: {
            input: [
                'resources/js/image-editor.js',
                'resources/css/image-editor.css',
            ],
            output: {
                entryFileNames: '[name].js',
                assetFileNames: '[name].[ext]',
            },
        },
        cssMinify: 'lightningcss',
        minify: true,
        target: 'es2017',
    },
    css: {
        lightningcss: {
            targets: browserslistToTargets(
                browserslist(['> 0.5%', 'last 2 versions', 'Firefox ESR', 'not dead'])
            ),
        },
        postcss: {
            plugins: [autoprefixer],
        },
    },
});
