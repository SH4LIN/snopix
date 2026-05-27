/**
 * Vite build for the plugins.php uninstall-confirm mini-app.
 *
 * Emits a single self-contained IIFE bundle so WordPress can load it as a
 * classic script without an importmap or module shim. CSS is extracted to a
 * sibling stylesheet so the PHP enqueue can register it independently.
 */
import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
	plugins: [react()],
	build: {
		outDir: './build',
		emptyOutDir: true,
		cssCodeSplit: false,
		rollupOptions: {
			input: 'src/index.tsx',
			output: {
				format: 'iife',
				entryFileNames: 'snopix-plugins-screen.js',
				assetFileNames: 'snopix-plugins-screen.[ext]',
				inlineDynamicImports: true,
			},
		},
	},
});
