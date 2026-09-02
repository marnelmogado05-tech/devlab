import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import babel from '@rolldown/plugin-babel';
import tailwindcss from '@tailwindcss/vite';
import react, { reactCompilerPreset } from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig, lazyPlugins } from 'vite-plus';

export default defineConfig({
    plugins: lazyPlugins(() => [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        inertia(),
        react(),
        babel({
            presets: [reactCompilerPreset()],
        }),
        tailwindcss(),
        wayfinder({
            formVariants: true,
        }),
    ]),
    server: {
        // Bind and advertise are different things. In Docker the dev server has
        // to listen on 0.0.0.0 to be reachable through the port mapping, but the
        // URL laravel-vite-plugin writes into public/hot — and the HMR socket the
        // browser dials — must be an address a browser can actually route to.
        // Left alone, --host 0.0.0.0 makes both of those 0.0.0.0 and the page
        // loads no assets at all. Override for a remote or renamed dev host.
        hmr: { host: process.env.VITE_HMR_HOST ?? 'localhost' },
        watch: {
            ignored: [
                '**/.agents/**',
                '**/.claude/**',
                '**/.cursor/**',
                '**/.junie/**',
                '**/vendor/**',
            ],
        },
    },
    /*
     * Frontend tests (§38), on the runner the toolchain already ships — `vp test`
     * is Vitest, so no separate runner was added.
     *
     * happy-dom rather than jsdom: it is markedly faster, and nothing here needs
     * the corners of the DOM spec that jsdom implements more completely.
     */
    test: {
        environment: 'happy-dom',
        globals: true,
        setupFiles: ['./resources/js/test/setup.ts'],
        include: ['resources/js/**/*.test.{ts,tsx}'],
        // Vitest would otherwise walk vendor/ and node_modules looking for specs.
        exclude: ['node_modules/**', 'vendor/**', 'public/**'],
    },

    lint: {
        ignorePatterns: [
            'vendor/**',
            'node_modules/**',
            'public/**',
            'bootstrap/ssr/**',
            'tailwind.config.js',
            'resources/js/actions/**',
            'resources/js/components/ui/*',
            'resources/js/routes/**',
            'resources/js/wayfinder/**',
        ],
        options: {
            denyWarnings: true,
            typeAware: true,
        },
    },
    fmt: {
        printWidth: 80,
        tabWidth: 4,
        singleQuote: true,
        semi: true,
        singleAttributePerLine: false,
        htmlWhitespaceSensitivity: 'css',
        ignorePatterns: [
            '.github/**',
            'composer.json',
            'resources/js/components/ui/*',
            'resources/views/mail/*',
        ],
        sortTailwindcss: {
            functions: ['clsx', 'cn', 'cva'],
            entryPoint: 'resources/css/app.css',
        },
    },
});
