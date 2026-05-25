/**
 * Legacy Tailwind JS configuration kept alongside `tailwind.config.ts` for
 * tooling that still autoloads the `.js` variant.
 *
 * Maps the same `snopix-*` colour tokens used by the typed config (see
 * `tailwind.config.ts`) but resolved at runtime via CSS variables so the host
 * page's theme can override them.
 */
export default {
	content: ['./src/**/*.{js,jsx,ts,tsx}'],
	theme: {
		extend: {
			colors: {
				'snopix-bg': 'var(--snopix-bg)',
				'snopix-surface': 'var(--snopix-surface)',
				'snopix-text': 'var(--snopix-text)',
				'snopix-muted': 'var(--snopix-muted)',
				'snopix-accent': 'var(--snopix-accent)',
				'snopix-success': 'var(--snopix-success)',
				'snopix-danger': 'var(--snopix-danger)',
				'snopix-warning': 'var(--snopix-warning)',
				'snopix-border': 'var(--snopix-border)',
			},
		},
	},
	plugins: [],
}
