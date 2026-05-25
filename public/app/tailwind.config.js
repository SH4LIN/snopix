/**
 * Tailwind configuration for the Snopix frontend search widget.
 *
 * Inherits design tokens from `shared/tailwind-preset.js`. Every Tailwind
 * utility is scoped under `.snopix-widget` via the `important` selector so
 * the bundle can drop into any host theme without leaking utility classes
 * into surrounding markup. Plain `corePlugins.preflight` is disabled — the
 * widget owns its own resets inside `src/styles/globals.css` so we don't
 * apply Tailwind's preflight to the host page.
 */
import snopixPreset from '../../shared/tailwind-preset.js'

export default {
	presets: [snopixPreset],
	content: ['./src/**/*.{js,jsx,ts,tsx}'],
	important: '.snopix-widget',
	corePlugins: {
		preflight: false,
	},
}
