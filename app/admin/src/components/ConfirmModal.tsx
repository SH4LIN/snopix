import { useEffect, type ReactNode } from 'react';
import { __ } from '@wordpress/i18n';
import { IconWarn } from './icons';

interface Props {
	open: boolean;
	title: string;
	message: ReactNode;
	subtitle?: string;
	confirmText?: string;
	cancelText?: string;
	danger?: boolean;
	loading?: boolean;
	icon?: ReactNode;
	onConfirm: () => void;
	onCancel: () => void;
}

/**
 * Apple-style confirm-or-cancel modal.
 *
 * Backdrop click and Escape both cancel (unless `loading`). The confirm button
 * switches to the destructive red style when `danger` is set. Optional `icon`
 * overrides the default warning glyph; `subtitle` adds a small caption beneath
 * the title.
 *
 * @param {Props} props Component props (see field-level JSDoc).
 *
 * @return {JSX.Element|null}
 */
export default function ConfirmModal({
	open,
	title,
	message,
	subtitle,
	confirmText,
	cancelText,
	danger,
	loading,
	icon,
	onConfirm,
	onCancel,
}: Props) {
	useEffect(() => {
		if (!open) {
			return;
		}
		const onKey = (e: KeyboardEvent) => {
			if (e.key === 'Escape' && !loading) {
				onCancel();
			}
		};
		document.addEventListener('keydown', onKey);
		return () => document.removeEventListener('keydown', onKey);
	}, [open, loading, onCancel]);

	if (!open) {
		return null;
	}

	const iconWrapBg = danger
		? 'bg-[rgba(255,59,48,0.1)] text-snopix-danger'
		: 'bg-snopix-accent-soft text-snopix-accent';

	return (
		<div
			className="snopix-modal-backdrop"
			onClick={() => !loading && onCancel()}
		>
			<div
				className="snopix-modal"
				onClick={(e) => e.stopPropagation()}
			>
				<div className="px-6 py-5 border-b border-snopix-border flex items-center gap-3">
					<div
						className={`w-9 h-9 rounded-full grid place-items-center ${iconWrapBg}`}
					>
						{icon ?? <IconWarn size={18} />}
					</div>
					<div>
						<div className="text-[15px] font-semibold">{title}</div>
						<div className="text-[13px] text-snopix-muted mt-0.5">
							{subtitle ??
								(danger
									? __(
											'Destructive · this cannot be undone',
											'snopix'
										)
									: __('Safe to run', 'snopix'))}
						</div>
					</div>
				</div>
				<div className="px-6 py-5 text-[13px] text-snopix-text leading-[1.55]">
					{message}
				</div>
				<div className="px-6 py-4 bg-snopix-surface flex justify-end gap-2">
					<button
						className="snopix-btn snopix-btn--neutral"
						onClick={onCancel}
						disabled={loading}
					>
						{cancelText ?? __('Cancel', 'snopix')}
					</button>
					<button
						className={`snopix-btn ${danger ? 'snopix-btn--danger' : ''}`}
						onClick={onConfirm}
						disabled={loading}
					>
						{loading
							? __('Working…', 'snopix')
							: (confirmText ?? __('Confirm', 'snopix'))}
					</button>
				</div>
			</div>
		</div>
	);
}
