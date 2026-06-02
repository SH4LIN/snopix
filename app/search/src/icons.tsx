/**
 * Inline SVG icons used by the frontend search widget. Ported from the
 * design canvas (`design/PixelScout/snopix-thumb.jsx`) and kept dependency
 * free so the bundle stays small.
 */
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
	const grid = [0, 1, 2, 3, 4]
	return (
		<svg
			width={size}
			height={size}
			viewBox="0 0 64 64"
			className={className}
			style={{ verticalAlign: '-3px' }}
		>
			{grid.map((r) =>
				grid.map((c) => (
					<rect
						key={`bg-${r}-${c}`}
						x={3 + c * 12}
						y={3 + r * 12}
						width="10"
						height="10"
						rx="1.5"
						fill="#d2d2d7"
					/>
				))
			)}
			<defs>
				<clipPath id="snopix-mark-lens">
					<circle cx="40" cy="24" r="16.5" />
				</clipPath>
			</defs>
			<g clipPath="url(#snopix-mark-lens)">
				{grid.map((r) =>
					grid.map((c) => {
						const hit = r === 1 && c === 3
						return (
							<rect
								key={`fg-${r}-${c}`}
								x={3 + c * 12}
								y={3 + r * 12}
								width="10"
								height="10"
								rx="1.5"
								fill={hit ? '#0071e3' : '#cfe4fb'}
							/>
						)
					})
				)}
			</g>
			<circle cx="40" cy="24" r="19" fill="none" stroke="#0071e3" strokeWidth="4" />
			<line
				x1="53.5"
				y1="37.5"
				x2="60"
				y2="44"
				stroke="#0071e3"
				strokeWidth="6.5"
				strokeLinecap="round"
			/>
		</svg>
	)
}
