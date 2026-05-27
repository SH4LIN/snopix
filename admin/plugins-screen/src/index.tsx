/**
 * Entry point for the plugins.php uninstall-confirm modal.
 *
 * Boots on DOMContentLoaded: attaches a click interceptor to the Snopix
 * Delete link, mounts a React portal root, and renders the modal when the
 * user clicks Delete. The modal handles its own data fetching and either
 * re-fires the original link (confirm) or unmounts (cancel).
 */

import { createRoot, type Root } from 'react-dom/client';
import { attachIntercept } from './intercept';
import { UninstallModal } from './UninstallModal';
import './styles.css';

interface BootData {
	restUrl: string;
	nonce: string;
	slug: string;
	dropOnUninstall: boolean;
}

declare global {
	interface Window {
		snopixPluginsScreen?: BootData;
	}
}

const ROOT_ID = 'snopix-uninstall-modal-root';

function ensureRoot(): { root: Root; container: HTMLDivElement } {
	let container = document.getElementById(ROOT_ID) as HTMLDivElement | null;
	if (!container) {
		container = document.createElement('div');
		container.id = ROOT_ID;
		document.body.appendChild(container);
	}
	const root = createRoot(container);
	return { root, container };
}

function bootstrap(): void {
	const data = window.snopixPluginsScreen;
	if (!data) {
		return;
	}

	attachIntercept(data.slug, ({ originalHref }) => {
		const { root, container } = ensureRoot();

		const teardown = () => {
			root.unmount();
			container.remove();
		};

		root.render(
			<UninstallModal
				restUrl={data.restUrl}
				nonce={data.nonce}
				dropOnUninstall={data.dropOnUninstall}
				onCancel={teardown}
				onConfirm={() => {
					teardown();
					window.location.assign(originalHref);
				}}
			/>
		);
	});
}

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', bootstrap, { once: true });
} else {
	bootstrap();
}
