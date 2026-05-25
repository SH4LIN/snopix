/**
 * Tailwind configuration for the Snopix admin app.
 *
 * Inherits design tokens (colours, radii, motion, fonts) from
 * `shared/tailwind-preset.js` so the admin app and frontend search widget
 * stay visually in sync. Only the `content` glob — which is bundle-specific —
 * lives here.
 */
import snopixPreset from '../../shared/tailwind-preset.js'

export default {
	presets: [snopixPreset],
	content: ['./src/**/*.{js,jsx,ts,tsx}'],
}
