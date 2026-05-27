(function () {
	var data = window.snopixUninstallGuard || {};
	var basename = data.basename || '';
	var message  = data.message || '';

	if ( ! basename ) {
		return;
	}

	document.addEventListener('click', function (e) {
		var link = e.target.closest('a.delete[href*="action=delete-selected"], a.delete[href*="action=delete-plugin"]');
		if (!link) {
			return;
		}
		if (link.href.indexOf(encodeURIComponent(basename)) === -1 && link.href.indexOf(basename) === -1) {
			return;
		}
		if (!window.confirm(message)) {
			e.preventDefault();
			e.stopImmediatePropagation();
		}
	}, true);
})();
