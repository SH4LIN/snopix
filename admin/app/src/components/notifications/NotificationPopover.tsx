import { useEffect, useRef } from 'react';
import { __, sprintf } from '@wordpress/i18n';
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
 * Desktop notification popover anchored to the header bell.
 *
 * Renders the active notice list as a tall, scrollable card with a header
 * ("Notifications" + count + Dismiss all) and a body that either lists notice
 * rows or shows the empty-state placeholder. Closes on outside click and on
 * Escape — the bell button itself toggles open/closed via the parent
 * (`useState`) so this component only needs to know how to clean up.
 *
 * @param {Props}    props         Component props.
 * @param {Function} props.onClose Called when the user clicks outside or
 *                                 presses Escape.
 *
 * @return {JSX.Element}
 */
export default function NotificationPopover({ onClose }: Props): JSX.Element {
	const ref = useRef<HTMLDivElement>(null);
	const { data, isLoading } = useNotices();
	const { mutate: dismissAll, isPending: isDismissingAll } = useDismissAllNotices();

	const notices: FeatureNotice[] = data ?? [];

	useEffect(() => {
		function onPointer(e: MouseEvent) {
			if (ref.current && !ref.current.contains(e.target as Node)) {
				onClose();
			}
		}
		function onKey(e: KeyboardEvent) {
			if (e.key === 'Escape') {
				onClose();
			}
		}
		document.addEventListener('mousedown', onPointer);
		document.addEventListener('keydown', onKey);
		return () => {
			document.removeEventListener('mousedown', onPointer);
			document.removeEventListener('keydown', onKey);
		};
	}, [onClose]);

	return (
		<div
			ref={ref}
			className="snopix-notice-popover"
			role="dialog"
			aria-label={__('Notifications', 'snopix')}
		>
			<div className="snopix-notice-popover__arrow" aria-hidden="true" />
			<div className="snopix-notice-popover__header">
				<div className="flex items-center gap-2">
					<span className="text-[14px] font-semibold text-snopix-text">
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
			<div className="snopix-notice-popover__body">
				{isLoading && notices.length === 0 ? (
					<div className="px-5 py-10 text-center text-[12px] text-snopix-muted">
						{__('Loading…', 'snopix')}
					</div>
				) : notices.length === 0 ? (
					<NotificationEmptyState />
				) : (
					notices.map((n) => (
						<NoticeItem key={n.id} notice={n} onCta={onClose} />
					))
				)}
			</div>
			<span className="sr-only">
				{sprintf(
					/* translators: %d: notification count */
					__('%d active notifications', 'snopix'),
					notices.length
				)}
			</span>
		</div>
	);
}
