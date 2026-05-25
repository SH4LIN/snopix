/**
 * Vite build configuration for the Snopix admin app.
 *
 * Bundles `src/main.tsx` (the React entry) into `dist/snopix-admin.js` and emits
 * any CSS / asset side-products as `snopix-admin.<ext>`. The names are stable so
 * `includes/admin/class-admin-page.php` can enqueue the bundle without a
 * manifest lookup.
 */
import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

export default defineConfig({
	plugins: [react()],
	build: {
		outDir: './dist',
		emptyOutDir: true,
		rollupOptions: {
			input: 'src/main.tsx',
			output: {
				entryFileNames: 'snopix-admin.js',
				assetFileNames: 'snopix-admin.[ext]',
			},
		},
	},
})
