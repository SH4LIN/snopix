import type { ReactNode, SVGProps } from 'react';

interface IconProps extends Omit<SVGProps<SVGSVGElement>, 'viewBox'> {
	size?: number;
	viewBox?: string;
	children?: ReactNode;
}

function I({
	size = 16,
	viewBox = '0 0 24 24',
	fill = 'none',
	stroke = 'currentColor',
	children,
	...rest
}: IconProps) {
	return (
		<svg
			width={size}
			height={size}
			viewBox={viewBox}
			fill={fill}
			stroke={stroke}
			strokeWidth="1.6"
			strokeLinecap="round"
			strokeLinejoin="round"
			style={{ display: 'inline-block', verticalAlign: '-2px', flexShrink: 0 }}
			{...rest}
		>
			{children}
		</svg>
	);
}

export const IconSearch = (p: IconProps) => (
	<I {...p}>
		<circle cx="11" cy="11" r="7" />
		<path d="M20 20l-3.5-3.5" />
	</I>
);
export const IconUpload = (p: IconProps) => (
	<I {...p}>
		<path d="M12 16V4M7 9l5-5 5 5M4 20h16" />
	</I>
);
export const IconImage = (p: IconProps) => (
	<I {...p}>
		<rect x="3" y="4" width="18" height="16" rx="2" />
		<circle cx="9" cy="10" r="1.6" />
		<path d="M21 16l-5-5-9 9" />
	</I>
);
export const IconLayers = (p: IconProps) => (
	<I {...p}>
		<path d="M12 3l9 5-9 5-9-5 9-5z" />
		<path d="M3 13l9 5 9-5" />
		<path d="M3 17l9 5 9-5" />
	</I>
);
export const IconTool = (p: IconProps) => (
	<I {...p}>
		<path d="M14 7a4 4 0 0 1 5.5-3.7L17 6l1 1 2.7-2.5A4 4 0 0 1 17 10l-7 7-3 1-1-1 1-3 7-7z" />
	</I>
);
export const IconSettings = (p: IconProps) => (
	<I {...p}>
		<circle cx="12" cy="12" r="3" />
		<path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.9.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.9l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.9.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.9-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.9V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z" />
	</I>
);
export const IconTrash = (p: IconProps) => (
	<I {...p}>
		<path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6M10 11v6M14 11v6" />
	</I>
);
export const IconCheck = (p: IconProps) => (
	<I {...p}>
		<path d="M5 12l4 4L19 6" />
	</I>
);
export const IconX = (p: IconProps) => (
	<I {...p}>
		<path d="M6 6l12 12M18 6L6 18" />
	</I>
);
export const IconRefresh = (p: IconProps) => (
	<I {...p}>
		<path d="M20 7v6h-6" />
		<path d="M4 17v-6h6" />
		<path d="M20 13a8 8 0 0 1-14.5 4M4 11a8 8 0 0 1 14.5-4" />
	</I>
);
export const IconBroom = (p: IconProps) => (
	<I {...p}>
		<path d="M19 4l-9 9" />
		<path d="M14 9l1 4-7 7-3-3 7-7 2 1" />
		<path d="M5 19l-2 2" />
	</I>
);
export const IconWarn = (p: IconProps) => (
	<I {...p}>
		<path d="M12 3l10 18H2L12 3z" />
		<path d="M12 10v5M12 18v0.5" />
	</I>
);
export const IconInfo = (p: IconProps) => (
	<I {...p}>
		<circle cx="12" cy="12" r="9" />
		<path d="M12 8v0.5M12 11v5" />
	</I>
);
export const IconLock = (p: IconProps) => (
	<I {...p}>
		<rect x="4" y="11" width="16" height="10" rx="2" />
		<path d="M8 11V8a4 4 0 0 1 8 0v3" />
	</I>
);
export const IconGlobe = (p: IconProps) => (
	<I {...p}>
		<circle cx="12" cy="12" r="9" />
		<path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18" />
	</I>
);
export const IconClock = (p: IconProps) => (
	<I {...p}>
		<circle cx="12" cy="12" r="9" />
		<path d="M12 7v5l3 2" />
	</I>
);
export const IconChevron = (p: IconProps) => (
	<I {...p}>
		<path d="M9 6l6 6-6 6" />
	</I>
);
export const IconFile = (p: IconProps) => (
	<I {...p}>
		<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-6-6z" />
		<path d="M14 3v6h6" />
	</I>
);
export const IconDot = (p: IconProps) => (
	<I {...p}>
		<circle cx="12" cy="12" r="3" />
	</I>
);
export const IconArrowRight = (p: IconProps) => (
	<I {...p}>
		<path d="M5 12h14M13 6l6 6-6 6" />
	</I>
);

interface BrandProps {
	size?: number;
}

export function BrandMark({ size = 22 }: BrandProps) {
	return (
		<svg
			width={size}
			height={size}
			viewBox="0 0 64 64"
			xmlns="http://www.w3.org/2000/svg"
		>
			<defs>
				<clipPath id="snopix-brand-lens">
					<circle cx="40" cy="24" r="16.5" />
				</clipPath>
			</defs>
			{[0, 1, 2, 3, 4].flatMap((r) =>
				[0, 1, 2, 3, 4].map((c) => (
					<rect
						key={`o-${r}-${c}`}
						x={3 + c * 12}
						y={3 + r * 12}
						width="10"
						height="10"
						rx="1.5"
						fill="#d2d2d7"
					/>
				))
			)}
			<g clipPath="url(#snopix-brand-lens)">
				{[0, 1, 2, 3, 4].flatMap((r) =>
					[0, 1, 2, 3, 4].map((c) => {
						const hit = r === 1 && c === 3;
						return (
							<rect
								key={`a-${r}-${c}`}
								x={3 + c * 12}
								y={3 + r * 12}
								width="10"
								height="10"
								rx="1.5"
								fill={hit ? '#0071e3' : '#cfe4fb'}
							/>
						);
					})
				)}
			</g>
			<circle
				cx="40"
				cy="24"
				r="19"
				fill="none"
				stroke="#0071e3"
				strokeWidth="4"
			/>
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
	);
}
