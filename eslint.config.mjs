import js from '@eslint/js';
import eslintPluginPrettier from 'eslint-plugin-prettier';
import eslintPluginReactHooks from 'eslint-plugin-react-hooks';
import eslintPluginImportX from 'eslint-plugin-import-x';
import tseslint from 'typescript-eslint';
import globals from 'globals';
import { fileURLToPath } from 'url';
import { dirname } from 'path';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

export default [
    // Ignore patterns for common build/coverage directories
    {
        ignores: [
            '**/*.min.js',
            'build/**',
            'public/**',
            'dist/**',
            'node_modules/**',
            'vendor/**',
            '.certs/**'
        ]
    },

    // JavaScript recommended config
    js.configs.recommended,

    ...tseslint.config(
        // TypeScript config with strict type checking and stylistic rules
        {
            files: ['**/*.ts', '**/*.tsx'],
            extends: [tseslint.configs.strictTypeChecked, tseslint.configs.stylisticTypeChecked],
            plugins: {
                prettier: eslintPluginPrettier,
                import: eslintPluginImportX,
            },
            languageOptions: {
                parserOptions: {
                    projectService: true,
                    tsconfigRootDir: __dirname,
                    ecmaFeatures: { jsx: true }
                },
                globals: {
                    ...globals.browser,
                    ...globals.node,
                }
            },
            rules: {
                '@typescript-eslint/array-type': 'error',
                '@typescript-eslint/consistent-indexed-object-style': ['warn', 'index-signature'],
                '@typescript-eslint/consistent-return': 'off',
                '@typescript-eslint/no-explicit-any': 'warn',
                '@typescript-eslint/no-unused-vars': [
                    'error',
                    {
                        vars: 'all',
                        varsIgnorePattern: '^_',
                        args: 'after-used',
                        argsIgnorePattern: '^_',
                        ignoreRestSiblings: true,
                    },
                ],
                '@typescript-eslint/no-unnecessary-condition': 'warn',
                '@typescript-eslint/prefer-nullish-coalescing': 'warn',
                '@typescript-eslint/restrict-template-expressions': ['error', { allowBoolean: true, allowNullish: true, allowNumber: true }],
                '@typescript-eslint/switch-exhaustiveness-check': 'error',
                '@typescript-eslint/consistent-type-imports': [
                    'warn',
                    {
                        fixStyle: 'separate-type-imports',
                        prefer: 'type-imports',
                    },
                ],
                '@typescript-eslint/no-floating-promises': 'error',
                '@typescript-eslint/no-misused-promises': ['error', { checksVoidReturn: { attributes: false } }],
                '@typescript-eslint/prefer-promise-reject-errors': 'warn',
                'prettier/prettier': 'error',
                'react-hooks/rules-of-hooks': 'error',
                'react-hooks/exhaustive-deps': 'warn',
                'import/order': [
                    'error',
                    {
                        groups: [
                            'builtin',
                            'external',
                            'internal',
                            'parent',
                            'sibling',
                            'index',
                            'object',
                            'type',
                        ],
                        'newlines-between': 'always',
                        alphabetize: {
                            order: 'asc',
                            caseInsensitive: true,
                        },
                    },
                ],
                'no-unused-vars': 'off',
                semi: 'off',
            }
        },
        {
            files: ['**/*.js', '**/*.jsx'],
            languageOptions: {
                parserOptions: {
                    projectService: false
                },
                globals: {
                    ...globals.browser,
                    ...globals.node,
                }
            }
        }
    ),

    // React hooks plugin configuration
    {
        plugins: {
            'react-hooks': eslintPluginReactHooks
        },
        rules: {
            ...eslintPluginReactHooks.configs.recommended.rules
        }
    }
];
