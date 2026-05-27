import { useMemo, useState, type ReactNode } from 'react';
import { useQuery } from '@tanstack/react-query';
import { apiFetch } from '../../lib/api';
import Tour from './Tour';

interface SettingsPayload {
	tour_completed: 'completed' | 'skipped' | null;
}

interface Props {
	children: ReactNode;
}

const SETTINGS_PATH = 'snopix/v1/settings';

function readTourQuery(): boolean {
	if (typeof window === 'undefined') {
		return false;
	}
	const params = new URLSearchParams(window.location.search);
	return params.get('tour') === '1';
}

function stripTourQuery(): void {
	if (typeof window === 'undefined') {
		return;
	}
	const url = new URL(window.location.href);
	if (!url.searchParams.has('tour')) {
		return;
	}
	url.searchParams.delete('tour');
	window.history.replaceState({}, '', url.toString());
}

/**
 * Decide whether the first-run walkthrough should be visible and render
 * `<Tour/>` accordingly.
 *
 * shouldRun = (?tour=1 in URL) OR (server-side tour_completed is null).
 *
 * `?tour=1` takes precedence so the post-activation redirect can re-open the
 * tour even after a prior dismissal in a different browser. Otherwise the
 * user_meta value is the authoritative gate.
 *
 * @param {Props} props Provider props.
 * @return {JSX.Element}
 */
export default function TourProvider({ children }: Props): JSX.Element {
	const { data, isLoading } = useQuery<SettingsPayload>({
		queryKey: ['snopix-settings-boot'],
		queryFn: () => apiFetch<SettingsPayload>(SETTINGS_PATH),
		staleTime: Infinity,
	});

	const [dismissed, setDismissed] = useState(false);

	const running = useMemo(() => {
		if (dismissed || isLoading) {
			return false;
		}
		const forced = readTourQuery();
		const completed = data?.tour_completed ?? null;
		return forced || completed === null;
	}, [dismissed, isLoading, data?.tour_completed]);

	const handleFinish = () => {
		setDismissed(true);
		stripTourQuery();
	};

	return (
		<>
			{children}
			{running && <Tour onFinish={handleFinish} />}
		</>
	);
}
