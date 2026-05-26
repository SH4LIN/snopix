import { useState } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { useNotices } from '../../hooks/use-notices';
import { IconBell } from '../icons';
import NotificationPopover from './NotificationPopover';
import NotificationSheet from './NotificationSheet';

interface Props {
	variant?: 'desktop' | 'mobile';
}

/**
 * Header notification trigger.
 *
 * Renders a bell button with an accent badge counting the active notice list.
 * On desktop the click opens an anchored popover; on mobile it opens a
 * bottom-sheet. The two surfaces share an underlying empty/list body
 * implementation so behaviour is identical across viewports.
 *
 * @param {Props}  props         Component props.
 * @param {string} props.variant `'desktop'` (square button + popover) or
 *                               `'mobile'` (round button + bottom sheet).
 *
 * @return {JSX.Element}
 */
export default function NotificationBell({ variant = 'desktop' }: Props): JSX.Element {
	const { data } = useNotices();
	const [open, setOpen] = useState(false);

	const count = data?.length ?? 0;
	const label = sprintf(
		/* translators: %d: notification count */
		__('Notifications (%d)', 'snopix'),
		count
	);

	const isMobile = variant === 'mobile';
	const buttonClass = isMobile
		? 'snopix-notice-bell snopix-notice-bell--mobile'
		: 'snopix-notice-bell snopix-notice-bell--desktop';

	return (
		<>
			<div className="snopix-notice-bell-wrap">
				<button
					type="button"
					className={buttonClass}
					onClick={() => setOpen((v) => !v)}
					aria-label={label}
					aria-expanded={open}
					aria-haspopup="dialog"
					data-open={open ? 'true' : 'false'}
				>
					<IconBell size={isMobile ? 16 : 18} />
					{count > 0 && (
						<span className="snopix-notice-bell__badge" aria-hidden="true">
							{count}
						</span>
					)}
				</button>
				{open && !isMobile && (
					<NotificationPopover onClose={() => setOpen(false)} />
				)}
			</div>
			{open && isMobile && (
				<NotificationSheet onClose={() => setOpen(false)} />
			)}
		</>
	);
}
