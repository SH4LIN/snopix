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
 * Three-card metrics row shown at the top of the Dashboard.
 *
 * Displays the current `total`, `indexed`, and `pending` counts from
 * `/wp-json/snopix/v1/status`. Falls back to em-dashes while data is still loading.
 *
 * @param {Props}   props        Component props.
 * @param {Status=} props.status Status payload from `/status`. Undefined while loading.
 *
 * @return {JSX.Element}
 */
export default function StatsBar({ status }: Props) {
	const cards = [
		{
			label: __('Total Images', 'snopix'),
			value: status?.total ?? '—',
			className: 'text-snopix-text',
		},
		{
			label: __('Indexed', 'snopix'),
			value: status?.indexed ?? '—',
			className: 'text-snopix-success',
		},
		{
			label: __('Pending', 'snopix'),
			value: status?.pending ?? '—',
			className: 'text-snopix-warning',
		},
		{
			label: __('Failed', 'snopix'),
			value: status?.failed ?? '—',
			className: 'text-snopix-danger',
		},
	];

	return (
		<div className="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
			{cards.map(({ label, value, className }) => (
				<div key={label} className="snopix-card">
					<div className={`text-[32px] font-bold ${className}`}>
						{typeof value === 'number'
							? value.toLocaleString()
							: value}
					</div>
					<div className="text-[13px] text-snopix-muted mt-1">
						{label}
					</div>
				</div>
			))}
		</div>
	);
}
