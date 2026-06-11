/**
 * Inline SVG icons used by the frontend search widget. Ported from the
 * design canvas (`design/PixelScout/snopix-thumb.jsx`) and kept dependency
 * free so the bundle stays small.
 */
import markSvg from '../../shared/snopix-mark.svg?raw'

type IconProps = { size?: number; className?: string }

export function IconUpload({ size = 20, className }: IconProps) {
	return (
		<svg
			width={size}
			height={size}
			viewBox="0 0 24 24"
			fill="none"
			stroke="currentColor"
			strokeWidth="1.8"
			strokeLinecap="round"
			strokeLinejoin="round"
			className={className}
		>
			<path d="M12 16V4M7 9l5-5 5 5M4 20h16" />
		</svg>
	)
}

export function IconReset({ size = 12, className }: IconProps) {
	return (
		<svg
			width={size}
			height={size}
			viewBox="0 0 24 24"
			fill="none"
			stroke="currentColor"
			strokeWidth="1.8"
			strokeLinecap="round"
			strokeLinejoin="round"
			className={className}
		>
			<path d="M3 12a9 9 0 1 0 3-6.7M3 4v5h5" />
		</svg>
	)
}

export function IconEmpty({ size = 22, className }: IconProps) {
	return (
		<svg
			width={size}
			height={size}
			viewBox="0 0 24 24"
			fill="none"
			stroke="currentColor"
			strokeWidth="1.6"
			strokeLinecap="round"
			strokeLinejoin="round"
			className={className}
		>
			<circle cx="11" cy="11" r="7" />
			<path d="M20 20l-3.5-3.5" />
			<path d="M8 11h6" />
		</svg>
	)
}

export function IconMark({ size = 18, className }: IconProps) {
	// Renders the shared mark (`app/shared/snopix-mark.svg`), the single source
	// also used by the wp-admin glue, so there is one copy of the artwork.
	return (
		<span
			className={className}
			style={{ display: 'inline-flex', width: size, height: size, verticalAlign: '-3px' }}
			dangerouslySetInnerHTML={{ __html: markSvg }}
		/>
	)
}
