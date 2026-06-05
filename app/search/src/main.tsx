/**
 * Snopix frontend search widget entry.
 *
 * Two ways the widget gets onto a page:
 *   1. Auto-boot - finds every `[data-snopix-search]` mount point already in
 *      the DOM at load (the shortcode + the server-rendered upload-page panel)
 *      and renders an isolated React tree into each.
 *   2. Programmatic - the IIFE bundle exposes `window.SnopixSearch.mount(el,
 *      opts)` so the wp-admin glue (`app/media`) can drop the same widget into
 *      Backbone-rendered surfaces (the media modal tab + library grid panel)
 *      whose nodes do not exist until the user opens them.
 *
 * The page-level `snopix_public` global is populated by `wp_localize_script`
 * (`Frontend\Shortcode::render()` on the front end, `Admin\Media_Surfaces` in
 * wp-admin) and supplies the REST URL + nonce.
 */
import { createRoot, type Root } from 'react-dom/client'
import SnopixWidget, { type WidgetVariant } from './SnopixWidget'
import './styles/globals.css'

type MountOptions = {
	variant?: string | null
	title?: string | null
	maxResults?: number | string | null
	embedded?: boolean
}

declare global {
	interface Window {
		snopix_public?: { rest_url: string; nonce: string }
		SnopixSearch?: { mount: (el: HTMLElement, options?: MountOptions) => Root }
	}
}

function parseVariant(value: string | null | undefined): WidgetVariant {
	if (value === 'inline' || value === 'narrow') {
		return value
	}
	return 'card'
}

function parseMaxResults(value: number | string | null | undefined, fallback: number): number {
	const parsed = Number(value)
	if (!Number.isFinite(parsed) || parsed <= 0) {
		return fallback
	}
	return Math.min(48, Math.floor(parsed))
}

/**
 * Render the widget into `el` and return its React root so the caller can
 * `unmount()` when the host view is torn down. Reads the REST config from the
 * `snopix_public` global, exactly like the auto-boot path.
 */
export function mount(el: HTMLElement, options: MountOptions = {}): Root {
	const root = createRoot(el)
	root.render(
		<SnopixWidget
			variant={parseVariant(options.variant)}
			title={options.title || 'Search by image'}
			maxResults={parseMaxResults(options.maxResults, 12)}
			restUrl={window.snopix_public?.rest_url ?? ''}
			nonce={window.snopix_public?.nonce ?? ''}
			embedded={options.embedded ?? false}
		/>
	)
	return root
}

function boot() {
	const nodes = document.querySelectorAll<HTMLElement>('[data-snopix-search]')
	nodes.forEach((el) => {
		if (el.dataset.snopixMounted) {
			return
		}
		el.dataset.snopixMounted = '1'
		mount(el, {
			variant: el.dataset.variant,
			title: el.dataset.title,
			maxResults: el.dataset.maxResults,
			embedded: el.dataset.embedded !== undefined,
		})
	})
}

// Expose the mount API for the wp-admin glue (`app/media`), which drops the
// widget into Backbone surfaces whose nodes are created after load. Vite's app
// build does not surface entry exports as a global, so assign it explicitly.
window.SnopixSearch = { mount }

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', boot, { once: true })
} else {
	boot()
}
