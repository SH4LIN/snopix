import { __ } from '@wordpress/i18n';

interface Status {
	total: number;
	indexed: number;
	pending: number;
	failed: number;
}

interface Props {
	status?: Status;
}

/**
 * Four-tile metrics row at the top of the Dashboard.
 *
 * Renders Total / Indexed / Pending / Failed from the `/status` payload. Each
 * tile uses tabular numerals so the values align visually regardless of digit
 * width.
 *
 * @param {Props}   props        Component props.
 * @param {Status=} props.status Status payload from `/status`. Undefined while loading.
 *
 * @return {JSX.Element}
 */
export default function StatsBar({ status }: Props) {
	const cards = [
		{
			label: __('Total', 'snopix'),
			value: status?.total,
			valueClass: '',
			delta: __('Attachments in library', 'snopix'),
		},
		{
			label: __('Indexed', 'snopix'),
			value: status?.indexed,
			valueClass: 'text-snopix-success-deep',
			delta: __('Fingerprinted and searchable', 'snopix'),
		},
		{
			label: __('Pending', 'snopix'),
			value: status?.pending,
			valueClass: 'text-snopix-warning-deep',
			delta: __('Queued for next cron tick', 'snopix'),
		},
		{
			label: __('Failed', 'snopix'),
			value: status?.failed,
			valueClass: 'text-snopix-danger-deep',
			delta: __('Unsupported or corrupted', 'snopix'),
		},
	];

	return (
		<div
			data-tour="dashboard-stats"
			className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-7"
		>
			{cards.map(({ label, value, valueClass, delta }) => (
				<div key={label} className="snopix-stat">
					<div className="snopix-stat__label">{label}</div>
					<div className={`snopix-stat__value ${valueClass}`}>
						{typeof value === 'number' ? value.toLocaleString() : '—'}
					</div>
					<div className="snopix-stat__delta">{delta}</div>
				</div>
			))}
		</div>
	);
}
