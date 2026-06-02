/**
 * PostCSS configuration consumed by Vite during the admin app build.
 *
 * Loads three plugins in order: `postcss-import` (inline `@import` statements),
 * `tailwindcss` (compile utility classes), and `autoprefixer` (vendor-prefix
 * the resulting CSS).
 */
export default {
	plugins: {
		'postcss-import': {},
		tailwindcss: {},
		autoprefixer: {},
	},
}
