import StatsBar from './StatsBar';
import ReindexButton from './ReindexButton';
import ImageTable from './ImageTable';
import SearchPreview from './SearchPreview';
import { useIndexStatus } from '../hooks/use-index-status';

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
