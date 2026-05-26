import { __ } from '@wordpress/i18n';
import { IconBell } from '../icons';

/**
 * "You're all caught up" placeholder rendered when the active notice list
 * is empty. Shared by the desktop popover and the mobile bottom sheet so
 * the empty state stays visually consistent across viewports.
 *
 * @return {JSX.Element}
 */
export default function NotificationEmptyState(): JSX.Element {
	return (
		<div className="snopix-notice-empty">
			<div className="snopix-notice-empty__icon" aria-hidden="true">
				<IconBell size={22} />
			</div>
			<div className="snopix-notice-empty__title">
				{__('You’re all caught up', 'snopix')}
			</div>
			<div className="snopix-notice-empty__text">
				{__('New tips and updates will show up here.', 'snopix')}
			</div>
		</div>
	);
}
