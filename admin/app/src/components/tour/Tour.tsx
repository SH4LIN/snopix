import {
	useCallback,
	useEffect,
	useLayoutEffect,
	useMemo,
	useRef,
	useState,
} from 'react';
import { __ } from '@wordpress/i18n';
import { useNavigate, useRouterState } from '@tanstack/react-router';
import { apiFetch } from '../../lib/api';
import { buildSteps, type TourStep } from './steps';

interface Props {
	onFinish: (status: 'completed' | 'skipped') => void;
}

interface Rect {
	top: number;
	left: number;
	width: number;
	height: number;
}

interface Placement {
	rect: Rect | null;
	tooltipTop: number;
	tooltipLeft: number;
	arrow: 'top' | 'bottom' | 'none';
}

const POST_TOUR_PATH = 'snopix/v1/tour/complete';
const SPOTLIGHT_PAD = 8;
const TOOLTIP_GAP = 14;
const TOOLTIP_W = 360;
const TOOLTIP_MARGIN = 16;

const IS_DEV = (
	import.meta as ImportMeta & { env?: { DEV?: boolean } }
).env?.DEV;

function readRect(selector: string): Rect | null {
	const el = document.querySelector(selector);
	if (!el) {
		if (IS_DEV) {
			console.warn(`[snopix tour] target not found: ${selector}`);
		}
		return null;
	}
	const r = (el as HTMLElement).getBoundingClientRect();
	if (r.width === 0 && r.height === 0) {
		return null;
	}
	return {
		top: r.top - SPOTLIGHT_PAD,
		left: r.left - SPOTLIGHT_PAD,
		width: r.width + SPOTLIGHT_PAD * 2,
		height: r.height + SPOTLIGHT_PAD * 2,
	};
}

function computePlacement(rect: Rect | null, viewportH: number, viewportW: number): Placement {
	if (!rect) {
		return {
			rect: null,
			tooltipTop: Math.max(viewportH / 2 - 140, 24),
			tooltipLeft: Math.max(viewportW / 2 - TOOLTIP_W / 2, TOOLTIP_MARGIN),
			arrow: 'none',
		};
	}

	const spaceBelow = viewportH - (rect.top + rect.height);
	const spaceAbove = rect.top;
	const placeBelow = spaceBelow >= 220 || spaceBelow >= spaceAbove;

	const tooltipTop = placeBelow
		? rect.top + rect.height + TOOLTIP_GAP
		: rect.top - TOOLTIP_GAP - 220;

	let tooltipLeft = rect.left + rect.width / 2 - TOOLTIP_W / 2;
	tooltipLeft = Math.max(TOOLTIP_MARGIN, tooltipLeft);
	tooltipLeft = Math.min(viewportW - TOOLTIP_W - TOOLTIP_MARGIN, tooltipLeft);

	return {
		rect,
		tooltipTop: Math.max(TOOLTIP_MARGIN, tooltipTop),
		tooltipLeft,
		arrow: placeBelow ? 'top' : 'bottom',
	};
}

async function scrollIntoView(selector: string): Promise<void> {
	const el = document.querySelector(selector);
	if (!el) {
		return;
	}
	(el as HTMLElement).scrollIntoView({ behavior: 'smooth', block: 'center' });
	await new Promise((r) => setTimeout(r, 320));
}

/**
 * Native first-run walkthrough. Renders a dimmed overlay with a rounded
 * cutout over the active step's target and a tooltip card positioned
 * adjacent to it. Step 1 has no target and renders centered as a welcome
 * card. Terminal lifecycle (finish or skip) POSTs `/tour/complete` so the
 * tour does not auto-open again.
 *
 * @param {Props} props Component props.
 * @return {JSX.Element}
 */
export default function Tour({ onFinish }: Props): JSX.Element {
	const steps: TourStep[] = useMemo(() => buildSteps(), []);
	const [index, setIndex] = useState(0);
	const [viewport, setViewport] = useState(() => ({
		w: window.innerWidth,
		h: window.innerHeight,
	}));
	const [placement, setPlacement] = useState<Placement>({
		rect: null,
		tooltipTop: 0,
		tooltipLeft: 0,
		arrow: 'none',
	});
	const dismissedRef = useRef(false);
	const navigate = useNavigate();
	const pathname = useRouterState({ select: (s) => s.location.pathname });

	const step = steps[index];
	const routeReady = !step.route || pathname === step.route;

	const reportCompletion = useCallback(
		async (status: 'completed' | 'skipped') => {
			try {
				await apiFetch({
					path: POST_TOUR_PATH,
					method: 'POST',
					data: { status },
				});
			} catch {
				/* swallow — local unmount still proceeds */
			}
		},
		[]
	);

	const finish = useCallback(
		(status: 'completed' | 'skipped') => {
			if (dismissedRef.current) {
				return;
			}
			dismissedRef.current = true;
			void reportCompletion(status);
			onFinish(status);
		},
		[onFinish, reportCompletion]
	);

	const next = useCallback(() => {
		if (index >= steps.length - 1) {
			finish('completed');
			return;
		}
		setIndex((i) => i + 1);
	}, [index, steps.length, finish]);

	const back = useCallback(() => {
		setIndex((i) => Math.max(0, i - 1));
	}, []);

	useEffect(() => {
		if (step.route && pathname !== step.route) {
			void navigate({ to: step.route });
		}
	}, [step.route, pathname, navigate]);

	useLayoutEffect(() => {
		if (!routeReady) {
			return;
		}
		let cancelled = false;
		const apply = () => {
			if (cancelled) {
				return;
			}
			const rect = step.target ? readRect(step.target) : null;
			setPlacement(computePlacement(rect, window.innerHeight, window.innerWidth));
		};
		if (step.target) {
			const t = window.setTimeout(() => {
				void scrollIntoView(step.target as string).then(apply);
			}, 60);
			return () => {
				cancelled = true;
				window.clearTimeout(t);
			};
		}
		apply();
		return () => {
			cancelled = true;
		};
	}, [step.target, routeReady]);

	useEffect(() => {
		const onResize = () => {
			setViewport({ w: window.innerWidth, h: window.innerHeight });
			const rect = step.target ? readRect(step.target) : null;
			setPlacement(computePlacement(rect, window.innerHeight, window.innerWidth));
		};
		window.addEventListener('resize', onResize);
		window.addEventListener('scroll', onResize, true);
		return () => {
			window.removeEventListener('resize', onResize);
			window.removeEventListener('scroll', onResize, true);
		};
	}, [step.target]);

	useEffect(() => {
		const onKey = (e: KeyboardEvent) => {
			if (e.key === 'Escape') {
				finish('skipped');
			} else if (e.key === 'ArrowRight' || e.key === 'Enter') {
				next();
			} else if (e.key === 'ArrowLeft') {
				back();
			}
		};
		window.addEventListener('keydown', onKey);
		return () => window.removeEventListener('keydown', onKey);
	}, [next, back, finish]);

	const isLast = index === steps.length - 1;
	const isFirst = index === 0;
	const hasTarget = Boolean(step.target && placement.rect);

	void viewport;

	return (
		<div
			className="snopix-tour"
			role="dialog"
			aria-modal="true"
			aria-labelledby="snopix-tour-title"
		>
			<div className="snopix-tour__overlay" aria-hidden="true">
				{hasTarget && placement.rect ? (
					<div
						className="snopix-tour__spotlight"
						style={{
							top: placement.rect.top,
							left: placement.rect.left,
							width: placement.rect.width,
							height: placement.rect.height,
						}}
					/>
				) : (
					<div className="snopix-tour__overlay-solid" />
				)}
			</div>

			<div
				className={`snopix-tour__card snopix-tour__card--arrow-${placement.arrow}`}
				style={{
					top: placement.tooltipTop,
					left: placement.tooltipLeft,
					width: TOOLTIP_W,
				}}
				key={index}
			>
				<div className="snopix-tour__meta">
					<span className="snopix-tour__counter">
						{__('Step', 'snopix')} {index + 1} / {steps.length}
					</span>
					<div className="snopix-tour__dots" aria-hidden="true">
						{steps.map((_, i) => (
							<span
								key={i}
								className={`snopix-tour__dot ${i === index ? 'is-active' : ''}`}
							/>
						))}
					</div>
				</div>

				<h3 id="snopix-tour-title" className="snopix-tour__title">
					{step.title}
				</h3>
				<p className="snopix-tour__content">{step.content}</p>

				<div className="snopix-tour__actions">
					<button
						type="button"
						className="snopix-tour__skip"
						onClick={() => finish('skipped')}
					>
						{__('Skip tour', 'snopix')}
					</button>
					<div className="snopix-tour__nav">
						{!isFirst && (
							<button
								type="button"
								className="snopix-btn snopix-btn--ghost snopix-btn--sm"
								onClick={back}
							>
								{__('Back', 'snopix')}
							</button>
						)}
						<button
							type="button"
							className="snopix-btn snopix-btn--sm"
							onClick={next}
						>
							{isLast
								? __('Finish', 'snopix')
								: isFirst
									? __('Get started', 'snopix')
									: __('Next', 'snopix')}
						</button>
					</div>
				</div>
			</div>
		</div>
	);
}
