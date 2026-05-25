/**
 * Vite build configuration for the Snopix frontend search widget.
 *
 * Bundles `src/main.tsx` into `dist/snopix-search.js` and emits the
 * accompanying stylesheet as `dist/snopix-search.css`. Stable filenames let
 * `includes/frontend/class-shortcode.php` enqueue the assets without a
 * manifest lookup. React is inlined so the widget has zero runtime deps on
 * the host page.
 */
import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

export default defineConfig({
	plugins: [react()],
	build: {
		outDir: './dist',
		emptyOutDir: true,
		cssCodeSplit: false,
		rollupOptions: {
			input: 'src/main.tsx',
			output: {
				entryFileNames: 'snopix-search.js',
				assetFileNames: 'snopix-search.[ext]',
			},
		},
	},
})
