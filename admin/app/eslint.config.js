/**
 * ESLint flat-config for the Pixel Scout admin app.
 *
 * Combines `@eslint/js` recommended rules with `typescript-eslint`'s
 * recommended rules, layers in the React Hooks + React Refresh plugins, and
 * disables stylistic rules that conflict with Prettier.
 */
import js from '@eslint/js'
import tseslint from 'typescript-eslint'
import reactHooks from 'eslint-plugin-react-hooks'
import reactRefresh from 'eslint-plugin-react-refresh'
import prettier from 'eslint-config-prettier'

export default tseslint.config(
	{ ignores: ['../dist', 'node_modules'] },
	js.configs.recommended,
	...tseslint.configs.recommended,
	{
		plugins: {
			'react-hooks': reactHooks,
			'react-refresh': reactRefresh,
		},
		rules: {
			...reactHooks.configs.recommended.rules,
			'react-refresh/only-export-components': ['warn', { allowConstantExport: true }],
		},
	},
	prettier,
)
