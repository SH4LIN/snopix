/**
 * Snopix reverse-image search widget for front-end shortcode.
 *
 * Renders into the markup emitted by `Frontend\Shortcode::render()`. Wires the
 * drop-zone / file-input pair to a multipart upload against
 * `/wp-json/snopix/v1/search` and paints the returned matches as clickable cards.
 *
 * @package Snopix
 */
(function () {
	'use strict';

	const widget = document.getElementById('snopix-search-widget');
	const dropZone = document.getElementById('snopix-drop-zone');
	const fileInput = document.getElementById('snopix-file-input');
	const results = document.getElementById('snopix-results');
	const errorEl = document.getElementById('snopix-error');

	if (!widget) return;

	// Click to open file picker
	dropZone.addEventListener('click', () => fileInput.click());

	// Drag over highlight
	dropZone.addEventListener('dragover', (e) => {
		e.preventDefault();
		dropZone.classList.add('snopix-drag-over');
	});
	dropZone.addEventListener('dragleave', () => dropZone.classList.remove('snopix-drag-over'));
	dropZone.addEventListener('drop', (e) => {
		e.preventDefault();
		dropZone.classList.remove('snopix-drag-over');
		const file = e.dataTransfer.files[0];
		if (file) handleFile(file);
	});

	fileInput.addEventListener('change', () => {
		const file = fileInput.files[0];
		if (file) handleFile(file);
	});

	/**
	 * Render four placeholder skeleton cards while the search request is in
	 * flight. Clears the previous results and hides any prior error.
	 *
	 * @return {void}
	 */
	function showSkeleton() {
		results.hidden = false;
		errorEl.hidden = true;
		const grid = document.createElement('div');
		grid.className = 'snopix-skeleton-grid';
		for (let i = 0; i < 4; i++) {
			const card = document.createElement('div');
			card.className = 'snopix-skeleton-card';
			grid.appendChild(card);
		}
		results.innerHTML = '';
		results.appendChild(grid);
	}

	/**
	 * Paint the result list returned by `/wp-json/snopix/v1/search`. Each card
	 * opens the matching attachment URL in a new tab when clicked. Falls back
	 * to a "no results" paragraph when the response is empty.
	 *
	 * @param {Array<{id:number,url:string,thumbnail:string,title:string,score:number,attachment_url:string}>} items Search results from the REST endpoint.
	 *
	 * @return {void}
	 */
	function showResults(items) {
		results.innerHTML = '';
		if (!items.length) {
			const noResults = document.createElement('p');
			noResults.className = 'snopix-no-results';
			noResults.textContent = 'No similar images found. Try a different image.';
			results.appendChild(noResults);
			results.hidden = false;
			return;
		}
		items.forEach((item) => {
			const card = document.createElement('div');
			card.className = 'snopix-result-card';
			card.style.cursor = 'pointer';
			card.addEventListener('click', () => window.open(escUrl(item.url), '_blank'));

			const img = document.createElement('img');
			img.src = escUrl(item.thumbnail || item.url);
			img.alt = escAttr(item.title);
			img.loading = 'lazy';

			const score = document.createElement('div');
			score.className = 'snopix-score';
			score.textContent = Math.round(item.score * 100) + '%';

			const title = document.createElement('div');
			title.className = 'snopix-result-title';
			title.textContent = item.title;

			card.appendChild(img);
			card.appendChild(score);
			card.appendChild(title);
			results.appendChild(card);
		});
		results.hidden = false;
	}

	/**
	 * Replace the result panel with a single error message.
	 *
	 * @param {string} msg Localised, user-facing error string.
	 *
	 * @return {void}
	 */
	function showError(msg) {
		errorEl.textContent = msg;
		errorEl.hidden = false;
		results.hidden = true;
	}

	/**
	 * Upload a single image to `/wp-json/snopix/v1/search` and pipe the response
	 * into {@link showResults} (or {@link showError} on failure).
	 *
	 * @param {File} file Image selected via input or drag-drop.
	 *
	 * @return {Promise<void>}
	 */
	async function handleFile(file) {
		errorEl.hidden = true;
		showSkeleton();

		const fd = new FormData();
		fd.append('file', file);

		try {
			const res = await fetch(snopix_public.rest_url + 'search', {
				method: 'POST',
				headers: { 'X-WP-Nonce': snopix_public.nonce },
				body: fd,
			});
			const data = await res.json().catch(() => null);

			if (!res.ok) {
				const message = data && typeof data.message === 'string'
					? data.message
					: 'Something went wrong. Try a different image.';
				showError(message);
				return;
			}

			if (!Array.isArray(data)) {
				showError('Unexpected response from the server.');
				return;
			}

			showResults(data);
		} catch (err) {
			showError('Something went wrong. Try a different image.');
		}
	}

	// XSS prevention helpers
	/**
	 * Escape single and double quotes for safe inclusion in an HTML attribute.
	 *
	 * @param {*} str Arbitrary value coerced to a string.
	 *
	 * @return {string} Escaped attribute-safe string.
	 */
	function escAttr(str) {
		return String(str).replace(/"/g, '&quot;').replace(/'/g, '&#x27;');
	}
	/**
	 * Validate a URL via the WHATWG `URL` parser and return its serialised
	 * form. Returns `'#'` for any input the parser rejects so a malformed URL
	 * never reaches the DOM.
	 *
	 * @param {string} str Untrusted URL string.
	 *
	 * @return {string} Safe URL or the `'#'` fallback.
	 */
	function escUrl(str) {
		try { return new URL(str).href; } catch { return '#'; }
	}
}());
