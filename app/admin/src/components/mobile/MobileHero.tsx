import type { ReactNode } from 'react';

interface Props {
	/** Optional uppercase label rendered above the title. */
	label?: ReactNode;
	/** Main heading. */
	title: ReactNode;
	/** Optional subtitle / supporting copy below the title. */
	subtitle?: ReactNode;
	/**
	 * Title size in pixels. Defaults to 24; Dashboard bumps to 26 to put
	 * extra weight on the "X indexed" hero stat.
	 */
	titleSize?: 24 | 26;
}

/**
 * Standard mobile screen hero header.
 *
 * Three-line stack — uppercase label, large display title, muted subtitle —
 * with the spacing/typography the mobile design specifies for every screen
 * in the admin app. Any line that's omitted is simply not rendered, so
 * Settings (title only) and Dashboard (label + title + subtitle) share the
 * same component.
 *
 * @param {Props} props Component props.
 *
 * @return {JSX.Element}
 */
export default function MobileHero({
	label,
	title,
	subtitle,
	titleSize = 24,
}: Props): JSX.Element {
	const titleClass =
		titleSize === 26
			? 'text-[26px] font-semibold tracking-[-0.015em] leading-[1.1]'
			: 'text-[24px] font-semibold tracking-[-0.015em] leading-[1.2]';

	return (
		<div className="px-[18px] pt-5 pb-3.5">
			{label && (
				<div className="text-[12px] font-medium text-snopix-muted uppercase tracking-[0.05em] mb-1">
					{label}
				</div>
			)}
			<div className={titleClass}>{title}</div>
			{subtitle && (
				<div className="text-[13px] text-snopix-muted mt-1">{subtitle}</div>
			)}
		</div>
	);
}
