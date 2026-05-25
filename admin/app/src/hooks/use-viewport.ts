import { useEffect, useState } from 'react';

export type Viewport = 'mobile' | 'desktop';

/**
 * Width breakpoint that flips the admin app between the WP-admin desktop
 * shell and the mobile single-column shell. Anything narrower than this —
 * phones plus portrait/landscape tablets — gets the mobile shell so the
 * thumb-reachable bottom-tab nav and grouped list layouts kick in well
 * before the desktop chrome would start to feel cramped.
 */
const MOBILE_MAX_WIDTH = 1279.98;

const MEDIA_QUERY = `(max-width: ${MOBILE_MAX_WIDTH}px)`;

function readViewport(): Viewport {
	if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') {
		return 'desktop';
	}
	return window.matchMedia(MEDIA_QUERY).matches ? 'mobile' : 'desktop';
}

/**
 * Observe the viewport width and report 'mobile' or 'desktop'. Subscribes to
 * the matchMedia change event so resizing the window (or rotating a tablet)
 * flips the shell live without a reload.
 *
 * @return {Viewport} Current viewport bucket.
 */
export function useViewport(): Viewport {
	const [viewport, setViewport] = useState<Viewport>(readViewport);

	useEffect(() => {
		if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') {
			return;
		}
		const mql = window.matchMedia(MEDIA_QUERY);
		const onChange = (event: MediaQueryListEvent) => {
			setViewport(event.matches ? 'mobile' : 'desktop');
		};
		mql.addEventListener('change', onChange);
		return () => mql.removeEventListener('change', onChange);
	}, []);

	return viewport;
}
