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
        // oxlint's default plugins, plus react for react/forbid-elements. Listing plugins replaces
        // the defaults, so they are named here to keep every rule that ran before.
        plugins: ['typescript', 'unicorn', 'oxc', 'react'],
        rules: {
            // Screens use the shadcn/ui primitives in components/ui (the js rule). The same check
            // runs in shipkit-core's FrontendPrimitivesTest; this one shows it in the editor.
            'react/forbid-elements': [
                'error',
                {
                    forbid: [
                        {
                            element: 'button',
                            message: 'use Button from @/components/ui/button',
                        },
                        {
                            element: 'input',
                            message:
                                'use Input, Checkbox, or RadioGroup from @/components/ui (a hidden field may stay)',
                        },
                        {
                            element: 'label',
                            message: 'use Label from @/components/ui/label',
                        },
                        {
                            element: 'select',
                            message: 'use Select from @/components/ui/select',
                        },
                        {
                            element: 'textarea',
                            message:
                                'use Textarea from @/components/ui/textarea (npx shadcn@latest add textarea)',
                        },
                        {
                            element: 'table',
                            message: 'use Table from @/components/ui/table',
                        },
                        {
                            element: 'dialog',
                            message: 'use Dialog from @/components/ui/dialog',
                        },
                    ],
                },
            ],
        },
        overrides: [
            {
                // The OAuth consent form posts to Passport with hidden fields only, which have no
                // look; FrontendPrimitivesTest still checks this file for anything visible.
                files: ['resources/js/pages/auth/oauth-authorize.tsx'],
                rules: { 'react/forbid-elements': 'off' },
            },
        ],
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
            'AGENTS.md',
            'CLAUDE.md',
            'boost.json',
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
