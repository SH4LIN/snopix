import { useEffect } from 'react';
import { createPortal } from 'react-dom';
import { __ } from '@wordpress/i18n';
import {
	useNotices,
	useDismissAllNotices,
	type FeatureNotice,
} from '../../hooks/use-notices';
import NoticeItem from './NoticeItem';
import NotificationEmptyState from './EmptyState';

interface Props {
	onClose: () => void;
}

/**
 * Mobile notification bottom sheet.
 *
 * Slides up from the bottom of the viewport with a translucent backdrop,
 * a grip handle, and the same content body as the desktop popover. Backdrop
 * tap and Escape both close. While open, the underlying page is prevented
 * from scrolling so the sheet stays in place during gestures.
 *
 * @param {Props}    props         Component props.
 * @param {Function} props.onClose Called when the user dismisses the sheet.
 *
 * @return {JSX.Element}
 */
export default function NotificationSheet({ onClose }: Props): JSX.Element {
	const { data, isLoading } = useNotices();
	const { mutate: dismissAll, isPending: isDismissingAll } = useDismissAllNotices();

	const notices: FeatureNotice[] = data ?? [];

	useEffect(() => {
		const previousOverflow = document.body.style.overflow;
		document.body.style.overflow = 'hidden';
		function onKey(e: KeyboardEvent) {
			if (e.key === 'Escape') {
				onClose();
			}
		}
		document.addEventListener('keydown', onKey);
		return () => {
			document.body.style.overflow = previousOverflow;
			document.removeEventListener('keydown', onKey);
		};
	}, [onClose]);

	const sheet = (
		<div
			className="snopix-notice-sheet-backdrop"
			role="dialog"
			aria-modal="true"
			aria-label={__('Notifications', 'snopix')}
			onClick={onClose}
		>
			<div
				className="snopix-notice-sheet"
				onClick={(e) => e.stopPropagation()}
			>
				<div className="snopix-notice-sheet__grip-wrap">
					<div className="snopix-notice-sheet__grip" aria-hidden="true" />
				</div>
				<div className="snopix-notice-sheet__header">
					<div className="flex items-center gap-2">
						<span className="text-[15px] font-semibold text-snopix-text">
							{__('Notifications', 'snopix')}
						</span>
						{notices.length > 0 && (
							<span className="snopix-pill snopix-pill--neutral text-[11px]">
								{notices.length}
							</span>
						)}
					</div>
					{notices.length > 0 && (
						<button
							type="button"
							className="snopix-notice-popover__dismiss-all"
							onClick={() => dismissAll()}
							disabled={isDismissingAll}
						>
							{__('Dismiss all', 'snopix')}
						</button>
					)}
				</div>
				<div className="snopix-notice-sheet__body">
					{isLoading && notices.length === 0 ? (
						<div className="px-5 py-10 text-center text-[13px] text-snopix-muted">
							{__('Loading…', 'snopix')}
						</div>
					) : notices.length === 0 ? (
						<NotificationEmptyState />
					) : (
						notices.map((n) => (
							<NoticeItem
								key={n.id}
								notice={n}
								onCta={onClose}
								dense
							/>
						))
					)}
				</div>
			</div>
		</div>
	);

	if (typeof document === 'undefined') {
		return sheet;
	}
	return createPortal(sheet, document.body);
}
