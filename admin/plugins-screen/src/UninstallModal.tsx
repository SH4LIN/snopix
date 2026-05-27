import { useEffect, useRef, useState } from 'react';
import { fetchStats, type Stats } from './stats';

interface Props {
	restUrl: string;
	nonce: string;
	dropOnUninstall: boolean;
	onCancel: () => void;
	onConfirm: () => void;
}

/**
 * Uninstall confirmation dialog rendered on wp-admin/plugins.php.
 *
 * Shows a small data summary (indexed image count, duplicate groups, whether
 * settings will be dropped) above the Cancel / Delete buttons. Fetches stats
 * lazily on mount via an AbortController so a quick Cancel does not leak a
 * pending request.
 *
 * @param {Props} props Modal props.
 * @return {JSX.Element}
 */
export function UninstallModal({
	restUrl,
	nonce,
	dropOnUninstall,
	onCancel,
	onConfirm,
}: Props): JSX.Element {
	const [stats, setStats] = useState<Stats | null>(null);
	const [error, setError] = useState<string | null>(null);
	const panelRef = useRef<HTMLDivElement>(null);

	useEffect(() => {
		const controller = new AbortController();
		fetchStats(restUrl, nonce, controller.signal)
			.then((data) => setStats(data))
			.catch((err: unknown) => {
				if (err instanceof DOMException && err.name === 'AbortError') {
					return;
				}
				setError(err instanceof Error ? err.message : String(err));
			});
		return () => controller.abort();
	}, [restUrl, nonce]);

	useEffect(() => {
		const onKey = (event: KeyboardEvent) => {
			if (event.key === 'Escape') {
				onCancel();
			}
		};
		document.addEventListener('keydown', onKey);
		panelRef.current?.focus();
		return () => document.removeEventListener('keydown', onKey);
	}, [onCancel]);

	const renderValue = (value: number | undefined) =>
		stats === null && error === null ? (
			<span className="snopix-um__loading">Loading…</span>
		) : value === undefined ? (
			'—'
		) : (
			value.toLocaleString()
		);

	return (
		<div
			className="snopix-um__backdrop"
			role="presentation"
			onClick={(event) => {
				if (event.target === event.currentTarget) {
					onCancel();
				}
			}}
		>
			<div
				ref={panelRef}
				className="snopix-um__panel"
				role="dialog"
				aria-modal="true"
				aria-labelledby="snopix-um-title"
				tabIndex={-1}
			>
				<div className="snopix-um__header">
					<h2 id="snopix-um-title" className="snopix-um__title">
						Delete Snopix?
					</h2>
					<p className="snopix-um__subtitle">
						Review what will be removed before you continue.
					</p>
				</div>
				<div className="snopix-um__body">
					<div className="snopix-um__stats">
						<div className="snopix-um__stat-label">Indexed images</div>
						<div className="snopix-um__stat-value">
							{renderValue(stats?.indexed)}
						</div>
						<div className="snopix-um__stat-label">
							Duplicate groups found
						</div>
						<div className="snopix-um__stat-value">
							{renderValue(stats?.duplicateGroups)}
						</div>
						<div className="snopix-um__stat-label">
							Settings &amp; index table
						</div>
						<div className="snopix-um__stat-value">
							{dropOnUninstall ? 'Will be deleted' : 'Will be kept'}
						</div>
					</div>
					{error && (
						<div className="snopix-um__warning">
							Could not load latest counts. Deletion will still
							proceed when confirmed.
						</div>
					)}
				</div>
				<div className="snopix-um__footer">
					<button
						type="button"
						className="snopix-um__btn snopix-um__btn--ghost"
						onClick={onCancel}
					>
						Cancel
					</button>
					<button
						type="button"
						className="snopix-um__btn snopix-um__btn--danger"
						onClick={onConfirm}
					>
						Delete plugin
					</button>
				</div>
			</div>
		</div>
	);
}
