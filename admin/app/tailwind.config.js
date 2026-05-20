/**
 * Legacy Tailwind JS configuration kept alongside `tailwind.config.ts` for
 * tooling that still autoloads the `.js` variant.
 *
 * Maps the same `ps-*` colour tokens used by the typed config (see
 * `tailwind.config.ts`) but resolved at runtime via CSS variables so the host
 * page's theme can override them.
 */
export default {
	content: ['./src/**/*.{js,jsx,ts,tsx}'],
	theme: {
		extend: {
			colors: {
				'ps-bg': 'var(--ps-bg)',
				'ps-surface': 'var(--ps-surface)',
				'ps-text': 'var(--ps-text)',
				'ps-muted': 'var(--ps-muted)',
				'ps-accent': 'var(--ps-accent)',
				'ps-success': 'var(--ps-success)',
				'ps-danger': 'var(--ps-danger)',
				'ps-warning': 'var(--ps-warning)',
				'ps-border': 'var(--ps-border)',
			},
		},
	},
	plugins: [],
}
