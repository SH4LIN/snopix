import { __ } from '@wordpress/i18n';
import StatsBar from './StatsBar';
import ReindexButton from './ReindexButton';
import SearchPreview from './SearchPreview';
import ImageTable from './ImageTable';
import { useIndexStatus } from '../hooks/use-index-status';

/**
 * Dashboard route — landing tab of the admin app.
 *
 * Stacks the page heading, reverse-image search panel, stat tiles, an inline
 * indexing-job progress card (when applicable), and the recently-indexed
 * table into a single full-width column. Stat tiles sit below the search
 * control so the primary action stays above the fold; feature notices are
 * surfaced through the header bell rather than inline on the dashboard.
 *
 * @return {JSX.Element}
 */
export default function Dashboard() {
	const { data: status } = useIndexStatus();

	return (
		<div>
			<h1 className="text-[26px] font-semibold tracking-[-0.015em] mb-1.5">
				{__('Dashboard', 'snopix')}
			</h1>
			<p className="text-[14px] text-snopix-muted mb-7">
				{__(
					'Reverse-image search across your indexed media library.',
					'snopix'
				)}
			</p>

			<SearchPreview />
			<StatsBar status={status} />
			<ReindexButton />
			<ImageTable />
		</div>
	);
}
