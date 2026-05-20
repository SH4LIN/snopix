import { __ } from '@wordpress/i18n';

interface Status {
	total: number;
	indexed: number;
	pending: number;
}

interface Props {
	status?: Status;
}

/**
 * Three-card metrics row shown at the top of the Dashboard.
 *
 * Displays the current `total`, `indexed`, and `pending` counts from
 * `/wp-json/ps/v1/status`. Falls back to em-dashes while data is still loading.
 *
 * @param {Props}   props        Component props.
 * @param {Status=} props.status Status payload from `/status`. Undefined while loading.
 *
 * @return {JSX.Element}
 */
export default function StatsBar({ status }: Props) {
	const cards = [
		{
			label: __('Total Images', 'pixel-scout'),
			value: status?.total ?? '—',
			className: 'text-ps-text',
		},
		{
			label: __('Indexed', 'pixel-scout'),
			value: status?.indexed ?? '—',
			className: 'text-ps-success',
		},
		{
			label: __('Pending', 'pixel-scout'),
			value: status?.pending ?? '—',
			className: 'text-ps-warning',
		},
	];

	return (
		<div className="grid grid-cols-3 gap-3 mb-4">
			{cards.map(({ label, value, className }) => (
				<div key={label} className="ps-card">
					<div className={`text-[32px] font-bold ${className}`}>
						{typeof value === 'number'
							? value.toLocaleString()
							: value}
					</div>
					<div className="text-[13px] text-ps-muted mt-1">
						{label}
					</div>
				</div>
			))}
		</div>
	);
}
