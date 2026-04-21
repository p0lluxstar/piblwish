import prettier from 'eslint-config-prettier';
import vue from 'eslint-plugin-vue';

export default [
    {
        ignores: ['node_modules/**', 'vendor/**', 'public/build/**', 'storage/**', 'bootstrap/cache/**'],
    },
    ...vue.configs['flat/recommended'],
    prettier,
    {
        files: ['**/*.{js,vue}'],
        languageOptions: {
            ecmaVersion: 2024,
            sourceType: 'module',
            globals: {
                console: 'readonly',
                document: 'readonly',
                process: 'readonly',
                window: 'readonly',
            },
        },
        rules: {
            'no-unused-vars': ['warn', { argsIgnorePattern: '^_' }],
            'no-undef': 'error',
            'no-console': process.env.NODE_ENV === 'production' ? 'warn' : 'warn',
            'no-debugger': process.env.NODE_ENV === 'production' ? 'warn' : 'off',
            'vue/multi-word-component-names': 'off',
        },
    },
];
