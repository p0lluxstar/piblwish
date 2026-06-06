import tseslint from '@typescript-eslint/eslint-plugin';
import tsParser from '@typescript-eslint/parser';
import prettier from 'eslint-config-prettier';
import simpleImportSort from 'eslint-plugin-simple-import-sort';
import vue from 'eslint-plugin-vue';
import vueParser from 'vue-eslint-parser';

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

    {
        files: ['**/*.{js,ts,vue}'],
        plugins: {
            '@typescript-eslint': tseslint,
            'simple-import-sort': simpleImportSort,
        },
        languageOptions: {
            ecmaVersion: 2024,
            sourceType: 'module',
            parser: vueParser,
            parserOptions: {
                parser: tsParser,
                extraFileExtensions: ['.vue'],
            },
            globals: {
                MouseEvent: 'readonly',
                console: 'readonly',
                document: 'readonly',
                process: 'readonly',
                window: 'readonly',
            },
        },
        rules: {
            'no-unused-vars': 'off',
            '@typescript-eslint/no-unused-vars': [
                'error',
                { argsIgnorePattern: '^_' },
            ],
            '@typescript-eslint/explicit-function-return-type': 'error',

            'no-undef': 'error',
            'no-console':
                process.env.NODE_ENV === 'production' ? 'warn' : 'warn',
            'no-debugger':
                process.env.NODE_ENV === 'production' ? 'warn' : 'off',
            'vue/multi-word-component-names': 'off',
            'simple-import-sort/imports': 'error',
            'simple-import-sort/exports': 'error',
            '@typescript-eslint/no-explicit-any': 'error',
        },
    },

    prettier,
];
