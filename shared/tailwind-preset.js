/**
 * Shared Tailwind preset for the Snopix admin app and frontend search widget.
 *
 * Exposes design tokens via CSS variables (defined in shared/tokens.css) and
 * the Apple-inspired font/radius/shadow/animation primitives both surfaces
 * use. Consumed via `presets: [snopixPreset]` in each app's tailwind.config.
 */
export default {
	theme: {
		extend: {
			colors: {
				'snopix-bg': 'var(--snopix-bg)',
				'snopix-surface': 'var(--snopix-surface)',
				'snopix-text': 'var(--snopix-text)',
				'snopix-muted': 'var(--snopix-muted)',
				'snopix-accent': 'var(--snopix-accent)',
				'snopix-accent-deep': 'var(--snopix-accent-deep)',
				'snopix-accent-soft': 'var(--snopix-accent-soft)',
				'snopix-success': 'var(--snopix-success)',
				'snopix-danger': 'var(--snopix-danger)',
				'snopix-warning': 'var(--snopix-warning)',
				'snopix-border': 'var(--snopix-border)',
				'snopix-border-strong': 'var(--snopix-border-strong)',
			},
			fontFamily: {
				sans: [
					'-apple-system',
					'BlinkMacSystemFont',
					'"SF Pro Display"',
					'"Inter"',
					'"Segoe UI"',
					'sans-serif',
				],
				mono: [
					'"JetBrains Mono"',
					'ui-monospace',
					'SFMono-Regular',
					'Menlo',
					'monospace',
				],
			},
			borderRadius: {
				card: '12px',
				input: '8px',
				pill: '20px',
			},
			boxShadow: {
				card: '0 1px 3px rgba(0,0,0,0.08)',
			},
			scale: {
				press: '0.98',
			},
			keyframes: {
				'snopix-spin': {
					'0%': { transform: 'rotate(0deg)' },
					'100%': { transform: 'rotate(360deg)' },
				},
				'snopix-progress-slide': {
					'0%': { backgroundPosition: '200% 0' },
					'100%': { backgroundPosition: '-200% 0' },
				},
				'snopix-toast-in': {
					from: { opacity: 0, transform: 'translate(-50%, 8px)' },
					to: { opacity: 1, transform: 'translate(-50%, 0)' },
				},
				'snopix-modal-fade': {
					from: { opacity: 0 },
					to: { opacity: 1 },
				},
				'snopix-modal-pop': {
					from: { opacity: 0, transform: 'translateY(8px) scale(0.98)' },
					to: { opacity: 1, transform: 'none' },
				},
				'snopix-skel': {
					'0%': { backgroundPosition: '200% 0' },
					'100%': { backgroundPosition: '-200% 0' },
				},
			},
			animation: {
				'snopix-spin': 'snopix-spin 1s linear infinite',
				'snopix-progress': 'snopix-progress-slide 1.6s ease-in-out infinite',
				'snopix-toast-in': 'snopix-toast-in 200ms ease-out',
				'snopix-modal-fade': 'snopix-modal-fade 160ms ease-out',
				'snopix-modal-pop': 'snopix-modal-pop 180ms ease-out',
				'snopix-skel': 'snopix-skel 1.4s ease-in-out infinite',
			},
		},
	},
	plugins: [],
}
