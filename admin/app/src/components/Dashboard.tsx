import StatsBar from './StatsBar';
import ReindexButton from './ReindexButton';
import ImageTable from './ImageTable';
import SearchPreview from './SearchPreview';
import { useIndexStatus } from '../hooks/use-index-status';

/**
 * Dashboard route — landing tab of the admin app.
 *
 * Composes the stat counters, re-index button, the paginated indexed-image
 * table, and the reverse-image search dropzone into a single responsive
 * two-column layout. Loads the live `/status` payload once at mount via
 * {@link useIndexStatus}.
 *
 * @return {JSX.Element}
 */
export default function Dashboard() {
	const { data: status } = useIndexStatus();

	return (
		<div className="flex flex-col gap-4">
			<StatsBar status={status} />
			<ReindexButton status={status} />
			<div className="grid grid-cols-1 xl:grid-cols-[1fr_380px] gap-4">
				<ImageTable />
				<SearchPreview />
			</div>
		</div>
	);
}
