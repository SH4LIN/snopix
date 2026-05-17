import { __ } from '@wordpress/i18n'

interface Status {
	total: number
	indexed: number
	pending: number
}

interface Props {
	status?: Status
}

export default function StatsBar({ status }: Props) {
	const cards = [
		{ label: __( 'Total Images', 'pixel-scout' ), value: status?.total ?? '—', className: 'text-ps-text' },
		{ label: __( 'Indexed', 'pixel-scout' ), value: status?.indexed ?? '—', className: 'text-ps-success' },
		{ label: __( 'Pending', 'pixel-scout' ), value: status?.pending ?? '—', className: 'text-ps-warning' },
	]

	return (
		<div className="grid grid-cols-3 gap-3 mb-4">
			{cards.map(({ label, value, className }) => (
				<div key={label} className="ps-card">
					<div className={`text-[32px] font-bold ${className}`}>
						{typeof value === 'number' ? value.toLocaleString() : value}
					</div>
					<div className="text-[13px] text-ps-muted mt-1">
						{label}
					</div>
				</div>
			))}
		</div>
	)
}
