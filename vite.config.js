import { defineConfig } from 'vite';

export default defineConfig({
    build: {
        outDir: 'dist',
        emptyOutDir: true,
        rollupOptions: {
            input: [
                'resources/js/image-constructor.js',
            ],
            output: {
                entryFileNames: '[name].js',
                assetFileNames: '[name].[ext]',
                format: 'iife',
                name: 'ImageConstructor',
            },
        },
        minify: true,
        target: 'es2020',
    },
});
