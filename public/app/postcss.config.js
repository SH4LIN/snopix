/**
 * PostCSS configuration for the Snopix frontend search widget build.
 *
 * Same pipeline as the admin app: `postcss-import` to inline shared token
 * `@import`s, `tailwindcss` to compile utilities, `autoprefixer` for vendor
 * prefixes.
 */
export default {
	plugins: {
		'postcss-import': {},
		tailwindcss: {},
		autoprefixer: {},
	},
}
