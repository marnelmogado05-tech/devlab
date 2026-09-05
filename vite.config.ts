import { fileURLToPath } from 'node:url';
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
        /*
         * The Laravel, Inertia and Wayfinder plugins are for building and serving
         * assets — they have no role in a component test, and running them there
         * is actively wrong: laravel-vite-plugin refuses to start in CI (Vitest
         * spins up a Vite server, which it reads as a dev server), and Wayfinder
         * shells out to artisan, which a frontend test should never need.
         *
         * Omitted rather than bypassed with LARAVEL_BYPASS_ENV_CHECK: the check
         * is correct, and the honest answer is not to load the plugin.
         */
        ...(process.env.VITEST
            ? []
            : [
                  laravel({
                      input: ['resources/css/app.css', 'resources/js/app.tsx'],
                      refresh: true,
                      /*
                       * Two families, split by who wrote the words. Archivo — a
                       * grotesque — carries everything a human wrote; JetBrains
                       * Mono carries everything the machine produced. Self
                       * hosted through Bunny rather than linked from Google, so
                       * a visitor's first paint does not depend on a third
                       * party and no request leaves for one.
                       */
                      fonts: [
                          bunny('Archivo', {
                              weights: [400, 500, 600, 700, 800],
                          }),
                          bunny('JetBrains Mono', {
                              weights: [400, 500, 700],
                          }),
                      ],
                  }),
                  inertia(),
              ]),
        react(),
        babel({
            presets: [reactCompilerPreset()],
        }),
        tailwindcss(),
        ...(process.env.VITEST
            ? []
            : [
                  wayfinder({
                      formVariants: true,
                  }),
              ]),
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
        /*
         * `@/` normally comes from laravel-vite-plugin, which is deliberately not
         * loaded here (see the plugin list). Declared explicitly so the tests
         * resolve imports without depending on a build plugin to do it.
         */
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
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
