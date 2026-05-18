import { useEffect } from 'react';
import { __ } from '@wordpress/i18n';

interface Props {
	open: boolean;
	title: string;
	message: string;
	confirmText?: string;
	cancelText?: string;
	danger?: boolean;
	loading?: boolean;
	onConfirm: () => void;
	onCancel: () => void;
}

export default function ConfirmModal({
	open,
	title,
	message,
	confirmText,
	cancelText,
	danger,
	loading,
	onConfirm,
	onCancel,
}: Props) {
	useEffect(() => {
		if (!open) return;
		const onKey = (e: KeyboardEvent) => {
			if (e.key === 'Escape' && !loading) onCancel();
		};
		document.addEventListener('keydown', onKey);
		return () => document.removeEventListener('keydown', onKey);
	}, [open, loading, onCancel]);

	if (!open) return null;

	const confirmBtn = danger
		? 'bg-ps-danger text-white border-ps-danger hover:opacity-90'
		: 'bg-ps-accent text-white border-ps-accent hover:opacity-90';

	return (
		<div
			className="fixed inset-0 z-[99999] bg-black/60 flex items-center justify-center p-4"
			onClick={() => !loading && onCancel()}
		>
			<div
				className="bg-ps-bg rounded-[12px] shadow-xl max-w-md w-full p-6"
				onClick={(e) => e.stopPropagation()}
			>
				<h3 className="text-[17px] font-semibold text-ps-text mb-2">
					{title}
				</h3>
				<p className="text-[14px] text-ps-muted mb-5 leading-snug">
					{message}
				</p>
				<div className="flex justify-end gap-2">
					<button
						className="ps-btn bg-ps-surface text-ps-text border border-ps-border"
						onClick={onCancel}
						disabled={loading}
					>
						{cancelText ?? __('Cancel', 'pixel-scout')}
					</button>
					<button
						className={`ps-btn border ${confirmBtn}`}
						onClick={onConfirm}
						disabled={loading}
					>
						{loading
							? __('Working…', 'pixel-scout')
							: (confirmText ?? __('Confirm', 'pixel-scout'))}
					</button>
				</div>
			</div>
		</div>
	);
}
