import type { ComponentType, FunctionComponent } from 'react';
import { useViewport } from '../hooks/use-viewport';

/**
 * Pair a desktop variant with a mobile variant and let a single component
 * dispatch between them based on the current viewport.
 *
 * The wrapper subscribes to `useViewport` so a window resize (or tablet
 * rotation) flips the live tree without a reload. Both variants receive the
 * exact same props, which is what lets one route entry serve both layouts
 * — TanStack Router only knows about the wrapped component.
 *
 * @template P                 Shared prop shape for both variants.
 * @param   {ComponentType<P>} Desktop Rendered when the viewport is ≥ the mobile cutoff.
 * @param   {ComponentType<P>} Mobile  Rendered when the viewport is below the mobile cutoff.
 * @param   {string}           [displayName] Optional debug label for React DevTools.
 *
 * @return {ComponentType<P>} Component that picks the variant at render time.
 */
export function withResponsive<P extends object>(
	Desktop: ComponentType<P>,
	Mobile: ComponentType<P>,
	displayName?: string
): FunctionComponent<P> {
	const Responsive: FunctionComponent<P> = (props) => {
		const viewport = useViewport();
		const Variant = viewport === 'mobile' ? Mobile : Desktop;
		return <Variant {...props} />;
	};
	Responsive.displayName =
		displayName ??
		`Responsive(${Desktop.displayName ?? Desktop.name ?? 'Desktop'}|${
			Mobile.displayName ?? Mobile.name ?? 'Mobile'
		})`;
	return Responsive;
}
