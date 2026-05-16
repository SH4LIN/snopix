(function () {
	'use strict';

	const widget = document.getElementById('ps-search-widget');
	const dropZone = document.getElementById('ps-drop-zone');
	const fileInput = document.getElementById('ps-file-input');
	const results = document.getElementById('ps-results');
	const errorEl = document.getElementById('ps-error');

	if (!widget) return;

	// Click to open file picker
	dropZone.addEventListener('click', () => fileInput.click());

	// Drag over highlight
	dropZone.addEventListener('dragover', (e) => {
		e.preventDefault();
		dropZone.classList.add('ps-drag-over');
	});
	dropZone.addEventListener('dragleave', () => dropZone.classList.remove('ps-drag-over'));
	dropZone.addEventListener('drop', (e) => {
		e.preventDefault();
		dropZone.classList.remove('ps-drag-over');
		const file = e.dataTransfer.files[0];
		if (file) handleFile(file);
	});

	fileInput.addEventListener('change', () => {
		const file = fileInput.files[0];
		if (file) handleFile(file);
	});

	function showSkeleton() {
		results.hidden = false;
		errorEl.hidden = true;
		const grid = document.createElement('div');
		grid.className = 'ps-skeleton-grid';
		for (let i = 0; i < 4; i++) {
			const card = document.createElement('div');
			card.className = 'ps-skeleton-card';
			grid.appendChild(card);
		}
		results.innerHTML = '';
		results.appendChild(grid);
	}

	function showResults(items) {
		results.innerHTML = '';
		if (!items.length) {
			const noResults = document.createElement('p');
			noResults.className = 'ps-no-results';
			noResults.textContent = 'No similar images found. Try a different image.';
			results.appendChild(noResults);
			results.hidden = false;
			return;
		}
		items.forEach((item) => {
			const card = document.createElement('div');
			card.className = 'ps-result-card';
			card.style.cursor = 'pointer';
			card.addEventListener('click', () => window.open(escUrl(item.url), '_blank'));

			const img = document.createElement('img');
			img.src = escUrl(item.thumbnail || item.url);
			img.alt = escAttr(item.title);
			img.loading = 'lazy';

			const score = document.createElement('div');
			score.className = 'ps-score';
			score.textContent = Math.round(item.score * 100) + '%';

			const title = document.createElement('div');
			title.className = 'ps-result-title';
			title.textContent = item.title;

			card.appendChild(img);
			card.appendChild(score);
			card.appendChild(title);
			results.appendChild(card);
		});
		results.hidden = false;
	}

	function showError(msg) {
		errorEl.textContent = msg;
		errorEl.hidden = false;
		results.hidden = true;
	}

	async function handleFile(file) {
		errorEl.hidden = true;
		showSkeleton();

		const fd = new FormData();
		fd.append('file', file);

		try {
			const res = await fetch(ps_public.rest_url + 'search', {
				method: 'POST',
				headers: { 'X-WP-Nonce': ps_public.nonce },
				body: fd,
			});
			if (!res.ok) throw new Error('HTTP ' + res.status);
			const data = await res.json();
			showResults(data);
		} catch (err) {
			showError('Something went wrong. Try a different image.');
		}
	}

	// XSS prevention helpers
	function escAttr(str) {
		return String(str).replace(/"/g, '&quot;').replace(/'/g, '&#x27;');
	}
	function escUrl(str) {
		try { return new URL(str).href; } catch { return '#'; }
	}
}());
