import { __ } from '@wordpress/i18n';
import { useNavigate } from '@tanstack/react-router';
import {
	IconCheck,
	IconInfo,
	IconLayers,
	IconRefresh,
	IconSettings,
	IconTool,
	IconUpload,
	IconWarn,
} from '../icons';
import {
	useDismissNotice,
	type FeatureNotice,
} from '../../hooks/use-notices';

const ICON_REGISTRY: Record<string, (p: { size?: number }) => JSX.Element> = {
	info: IconInfo,
	layers: IconLayers,
	tool: IconTool,
	settings: IconSettings,
	upload: IconUpload,
	refresh: IconRefresh,
	warn: IconWarn,
	check: IconCheck,
};

const ROUTE_MAP: Record<string, string> = {
	dashboard: '/dashboard',
	duplicates: '/duplicates',
	tools: '/tools',
	settings: '/settings',
};

interface Props {
	notice: FeatureNotice;
	onCta?: () => void;
	dense?: boolean;
}

/**
 * Single row inside a notification surface (popover or bottom sheet).
 *
 * Layout matches the design: square accent-tinted icon, title + optional badge,
 * body copy, then a CTA / Dismiss control pair. The CTA navigates in-app via
 * the route slug attached to the notice; when no recognised route is set the
 * `cta_url` is opened in a new tab. The `onCta` prop is invoked after
 * navigation so callers (popover/sheet) can close themselves.
 *
 * @param {Props} props Component props.
 *
 * @return {JSX.Element}
 */
export default function NoticeItem({ notice, onCta, dense }: Props): JSX.Element {
	const navigate = useNavigate();
	const { mutate: dismiss, isPending: isDismissing } = useDismissNotice();
	const Icon = ICON_REGISTRY[notice.icon] ?? IconInfo;
	const hasCta =
		notice.cta_label !== '' &&
		(ROUTE_MAP[notice.cta_route] !== undefined || notice.cta_url !== '');

	function handleCta() {
		const target = ROUTE_MAP[notice.cta_route];
		if (target) {
			navigate({ to: target });
		} else if (notice.cta_url) {
			window.open(notice.cta_url, '_blank', 'noopener,noreferrer');
		}
		onCta?.();
	}

	return (
		<div
			className={`snopix-notice-item ${dense ? 'snopix-notice-item--dense' : ''}`}
		>
			<div className="snopix-notice-item__icon" aria-hidden="true">
				<Icon size={16} />
			</div>
			<div className="snopix-notice-item__body">
				<div className="snopix-notice-item__title">{notice.title}</div>
				<div className="snopix-notice-item__text">{notice.body}</div>
				<div className="snopix-notice-item__actions">
					{hasCta && (
						<button
							type="button"
							className="snopix-btn snopix-btn--sm"
							onClick={handleCta}
						>
							{notice.cta_label}
						</button>
					)}
					<button
						type="button"
						className="snopix-notice-item__dismiss-link"
						onClick={() => dismiss(notice.id)}
						disabled={isDismissing}
					>
						{__('Dismiss', 'snopix')}
					</button>
				</div>
			</div>
		</div>
	);
}
