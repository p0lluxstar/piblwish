import prettier from 'eslint-config-prettier';
import vue from 'eslint-plugin-vue';
import tseslint from 'typescript-eslint';

export default [
    {
        ignores: [
            'node_modules/**',
            'vendor/**',
            'public/build/**',
            'storage/**',
            'bootstrap/cache/**',
        ],
    },

    ...vue.configs['flat/recommended'],
    ...tseslint.configs.recommended,

    {
        files: ['**/*.{js,ts,vue}'],
        languageOptions: {
            ecmaVersion: 2024,
            sourceType: 'module',
            parserOptions: {
                parser: '@typescript-eslint/parser',
            },
            globals: {
                console: 'readonly',
                document: 'readonly',
                process: 'readonly',
                window: 'readonly',
            },
        },
        rules: {
            'no-unused-vars': 'off',
            '@typescript-eslint/no-unused-vars': [
                'warn',
                { argsIgnorePattern: '^_' },
            ],

            'no-undef': 'off',
            'no-console':
                process.env.NODE_ENV === 'production' ? 'warn' : 'warn',
            'no-debugger':
                process.env.NODE_ENV === 'production' ? 'warn' : 'off',
            'vue/multi-word-component-names': 'off',
        },
    },

    prettier,
];
