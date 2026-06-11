/**
 * Snopix wp-admin media glue.
 *
 * Drops the existing frontend search widget (`app/search` -> the
 * `window.SnopixSearch.mount` API, in `embedded` mode) into the WordPress media
 * surfaces, wrapped in the branded `sxwp-*` chrome from the design. The widget
 * and the `snopix/v1/search` endpoint stay the source of truth; this file only
 * places a host node into each surface and hands it to the widget.
 *
 *   1. Upload New Media page (`media-new.php`) - PHP renders a segmented toggle
 *      + a `[data-snopix-search]` panel the widget auto-boots into; here we only
 *      wire the toggle that swaps the native uploader for it.
 *   2. Media modal (featured image / "Add Media") - a "Search by image" router
 *      tab on the Backbone `wp.media` Select/Post frames.
 *   3. Media Library grid (`upload.php?mode=grid`) - a trigger to the right of
 *      the search box + a panel the widget mounts into on first open.
 *
 * Every entry point self-gates on the DOM / globals it needs, so loading this
 * bundle on a screen that lacks a given surface is a no-op.
 */
import './styles.css'
import markSvg from '../../shared/snopix-mark.svg?raw'

type SnopixRoot = { unmount(): void }

type SnopixMediaConfig = {
	variant: string
	maxResults: number
	i18n: {
		trigger: string
		panelTitle: string
	}
}

declare global {
	interface Window {
		// `wp.media` is dynamically typed Backbone; keep it loose.
		wp?: any
		SnopixSearch?: {
			mount(
				el: HTMLElement,
				options?: {
					variant?: string
					title?: string
					maxResults?: number
					embedded?: boolean
				}
			): SnopixRoot
		}
		snopix_media?: SnopixMediaConfig
	}
}

function config(): SnopixMediaConfig | null {
	return window.snopix_media ?? null
}

/**
 * Render the search widget into `el` (embedded - the host supplies chrome),
 * returning its React root for later teardown.
 */
function mountWidget(el: HTMLElement): SnopixRoot | null {
	const cfg = config()
	if (!window.SnopixSearch || !cfg) {
		return null
	}
	return window.SnopixSearch.mount(el, {
		variant: cfg.variant,
		title: cfg.i18n.panelTitle,
		maxResults: cfg.maxResults,
		embedded: true,
	})
}

/** Wrap the shared mark (`app/shared/snopix-mark.svg`) at a given pixel size. */
function mark(size: number): string {
	return `<span class="sxwp-mark" style="width:${size}px;height:${size}px">${markSvg}</span>`
}

const CLOSE_SVG =
	'<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>'

/** Escape a localized string before it goes into an innerHTML context. */
function escapeHtml(value: string): string {
	return value
		.replace(/&/g, '&amp;')
		.replace(/</g, '&lt;')
		.replace(/>/g, '&gt;')
		.replace(/"/g, '&quot;')
}

/**
 * Toggle a body flag while a Snopix drop area is the active surface. CSS uses
 * it to hide WordPress's full-window upload overlay (`.uploader-window`), whose
 * dropzone is the whole modal/page, so a dropped image reaches the widget and
 * searches instead of being uploaded.
 */
function setDropGuard(active: boolean): void {
	document.body.classList.toggle('snopix-search-active', active)
}

/* ── Surface 1 · Upload New Media page ─────────────────────────────────────
 * The panel (`#snopix-upload-panel`) is server-rendered and already holds a
 * `[data-snopix-search]` node the widget auto-boots into, so we only toggle
 * visibility between the native uploader and the Snopix panel. */
function initUpload(): void {
	const toggle = document.getElementById('snopix-upload-toggle')
	const panel = document.getElementById('snopix-upload-panel')
	if (!toggle || !panel) {
		return
	}

	const native = [
		document.getElementById('plupload-upload-ui'),
		document.getElementById('html-upload-ui'),
		document.querySelector<HTMLElement>('#file-form .max-upload-size'),
	].filter((node): node is HTMLElement => node !== null)

	const buttons = Array.from(
		toggle.querySelectorAll<HTMLButtonElement>('button[data-mode]')
	)

	const setMode = (mode: string): void => {
		const search = mode === 'search'
		panel.hidden = !search
		setDropGuard(search)
		native.forEach((node) => {
			node.style.display = search ? 'none' : ''
		})
		buttons.forEach((button) => {
			const active = button.dataset.mode === mode
			button.classList.toggle('is-active', active)
			button.setAttribute('aria-selected', active ? 'true' : 'false')
		})
	}

	buttons.forEach((button) => {
		button.addEventListener('click', () => setMode(button.dataset.mode ?? 'upload'))
	})

	setMode('upload')
}

/* ── Surface 2 · Media modal (Backbone wp.media) ───────────────────────────
 * Add a snopix-blue "Search by image" tab to the Select (featured image,
 * generic picker) and Post ("Add Media") frames. Search-only: a result links
 * out to its attachment page, it does not write into the frame selection. */
function installModalTab(): void {
	const wp = window.wp
	const cfg = config()
	if (!wp?.media?.view?.MediaFrame || !wp.media.View || !cfg) {
		return
	}

	// Content-region view: a powered-by line + the embedded widget host.
	const SnopixContent = wp.media.View.extend({
		className: 'snopix-media-tab',
		_root: null as SnopixRoot | null,
		render() {
			const by = document.createElement('div')
			by.className = 'snopix-media-tab__by'
			by.innerHTML = '<span class="sxwp-by">powered by snopix</span>'

			const widget = document.createElement('div')
			widget.className = 'snopix-media-tab__widget'

			this.el.appendChild(by)
			this.el.appendChild(widget)
			this._root = mountWidget(widget)
			setDropGuard(true)
			return this
		},
		remove() {
			if (this._root) {
				this._root.unmount()
				this._root = null
			}
			setDropGuard(false)
			return wp.media.View.prototype.remove.apply(this, arguments)
		},
	})

	const tabHtml = `${mark(15)}<span>${escapeHtml(cfg.i18n.trigger)}</span>`

	const withSnopixTab = (Frame: any) =>
		Frame.extend({
			browseRouter(routerView: any) {
				Frame.prototype.browseRouter.apply(this, arguments)
				routerView.set({
					snopix: { html: tabHtml, priority: 60 },
				})
			},
			bindHandlers() {
				Frame.prototype.bindHandlers.apply(this, arguments)
				this.on('content:create:snopix', (contentRegion: any) => {
					contentRegion.view = new SnopixContent({ controller: this })
				})
				// Closing the modal hides the frame without removing its views,
				// so the drop guard set by a still-mounted Snopix tab would leak
				// onto other frames; sync it with the modal's lifecycle instead.
				this.on('close', () => setDropGuard(false))
				this.on('open', () => setDropGuard(this.content.mode() === 'snopix'))
			},
		})

	wp.media.view.MediaFrame.Select = withSnopixTab(wp.media.view.MediaFrame.Select)
	wp.media.view.MediaFrame.Post = withSnopixTab(wp.media.view.MediaFrame.Post)
}

/* ── Surface 3 · Media Library grid (`upload.php?mode=grid`) ────────────────
 * The grid toolbar (filters · Bulk select · search) is Backbone-rendered after
 * load, so wait for it, drop the trigger to the right of the search box, and
 * put the panel right under the toolbar. The widget mounts lazily on open. */
function initGrid(): void {
	const wrap = document.getElementById('wp-media-grid')
	const cfg = config()
	if (!wrap || !cfg) {
		return
	}

	const inject = (toolbar: HTMLElement): void => {
		if (toolbar.querySelector('.sxwp-trigger')) {
			return
		}

		const button = document.createElement('button')
		button.type = 'button'
		button.className = 'sxwp-trigger'
		button.setAttribute('aria-expanded', 'false')
		button.innerHTML = mark(16)
		const triggerLabel = document.createElement('span')
		triggerLabel.textContent = cfg.i18n.trigger
		button.appendChild(triggerLabel)

		// Right of the search box (the search lives in the primary region).
		// WP caps that region at max-width:33%, so flag it for the CSS that
		// lifts the cap + lets the trigger wrap instead of overflowing.
		const primary = toolbar.querySelector('.media-toolbar-primary')
		if (primary) {
			primary.classList.add('snopix-has-trigger')
			primary.appendChild(button)
		} else {
			toolbar.appendChild(button)
		}

		const panel = document.createElement('div')
		panel.className = 'sxwp-panel'
		panel.hidden = true

		const titleLabel = document.createElement('span')
		titleLabel.textContent = cfg.i18n.panelTitle
		const close = document.createElement('button')
		close.type = 'button'
		close.className = 'sxwp-panel__close'
		close.setAttribute('aria-label', 'Close')
		close.innerHTML = CLOSE_SVG
		const host = document.createElement('div')
		host.className = 'sxwp-panel__body'

		const head = document.createElement('div')
		head.className = 'sxwp-panel__head'
		const title = document.createElement('div')
		title.className = 'sxwp-panel__title'
		title.innerHTML = mark(18)
		title.appendChild(titleLabel)
		const rightSide = document.createElement('div')
		rightSide.className = 'sxwp-panel__right'
		const by = document.createElement('span')
		by.className = 'sxwp-by'
		by.textContent = 'powered by snopix'
		rightSide.append(by, close)
		head.append(title, rightSide)
		panel.append(head, host)
		toolbar.insertAdjacentElement('afterend', panel)

		let root: SnopixRoot | null = null
		const setOpen = (open: boolean): void => {
			panel.hidden = !open
			setDropGuard(open)
			button.classList.toggle('is-active', open)
			button.setAttribute('aria-expanded', open ? 'true' : 'false')
			if (open && !root) {
				root = mountWidget(host)
			}
		}

		button.addEventListener('click', () => setOpen(panel.hidden))
		close.addEventListener('click', () => setOpen(false))
	}

	const existing = wrap.querySelector<HTMLElement>('.media-toolbar')
	if (existing) {
		inject(existing)
		return
	}

	const observer = new MutationObserver(() => {
		const toolbar = wrap.querySelector<HTMLElement>('.media-toolbar')
		if (toolbar) {
			observer.disconnect()
			inject(toolbar)
		}
	})
	observer.observe(wrap, { childList: true, subtree: true })
}

function boot(): void {
	initUpload()
	initGrid()
}

// Modal frames are created on user action; reassign the globals as early as
// the bundle evaluates so a frame opened later inherits the Snopix tab.
installModalTab()

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', boot, { once: true })
} else {
	boot()
}
