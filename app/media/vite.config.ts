/**
 * Vite build configuration for the Snopix wp-admin media glue.
 *
 * Bundles `src/index.ts` into `dist/snopix-media.js` plus `snopix-media.css`.
 * Stable filenames let `includes/admin/class-media-surfaces.php` enqueue the
 * assets without a manifest lookup. The bundle has no runtime deps - it reads
 * the WordPress `wp.media` global and the React widget's `window.SnopixSearch`
 * mount API, both enqueued ahead of it.
 */
import { defineConfig } from 'vite'

export default defineConfig({
	build: {
		outDir: '../../assets/media',
		emptyOutDir: true,
		cssCodeSplit: false,
		rollupOptions: {
			input: 'src/index.ts',
			output: {
				format: 'iife',
				name: 'SnopixMedia',
				entryFileNames: 'snopix-media.js',
				assetFileNames: 'snopix-media.[ext]',
			},
		},
	},
})
