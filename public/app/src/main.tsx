/**
 * Snopix frontend search widget entry.
 *
 * Finds every `[data-snopix-search]` mount point on the page, reads its
 * inline config from `data-*` attributes, and renders an isolated React tree
 * into each. Lets one page host multiple shortcode instances (e.g. an inline
 * variant in an article body + a card variant in a sidebar) without state
 * cross-talk.
 *
 * The page-level `snopix_public` global is populated by `wp_localize_script`
 * in `Frontend\Shortcode::render()` and supplies the REST URL + nonce.
 */
import { createRoot } from 'react-dom/client'
import SnopixWidget, { type WidgetVariant } from './SnopixWidget'
import './styles/globals.css'

declare global {
	interface Window {
		snopix_public?: { rest_url: string; nonce: string }
	}
}

function parseVariant(value: string | null | undefined): WidgetVariant {
	if (value === 'inline' || value === 'narrow') {
		return value
	}
	return 'card'
}

function parseMaxResults(value: string | null | undefined, fallback: number): number {
	const parsed = Number(value)
	if (!Number.isFinite(parsed) || parsed <= 0) {
		return fallback
	}
	return Math.min(48, Math.floor(parsed))
}

function mount(el: HTMLElement) {
	const variant = parseVariant(el.dataset.variant)
	const title = el.dataset.title || 'Search by image'
	const maxResults = parseMaxResults(el.dataset.maxResults, 12)

	createRoot(el).render(
		<SnopixWidget
			variant={variant}
			title={title}
			maxResults={maxResults}
			restUrl={window.snopix_public?.rest_url ?? ''}
			nonce={window.snopix_public?.nonce ?? ''}
		/>
	)
}

function boot() {
	const nodes = document.querySelectorAll<HTMLElement>('[data-snopix-search]')
	nodes.forEach(mount)
}

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', boot, { once: true })
} else {
	boot()
}
