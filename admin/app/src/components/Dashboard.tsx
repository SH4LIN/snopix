import { __ } from '@wordpress/i18n'
import StatsBar from './StatsBar'
import ReindexButton from './ReindexButton'
import ImageTable from './ImageTable'
import SearchPreview from './SearchPreview'
import { useIndexStatus } from '../hooks/use-index-status'

export default function Dashboard() {
	const { data: status } = useIndexStatus()

	return (
		<div id="pixel-scout-app" className="p-6 max-w-[1200px]">
			<h1 className="text-[28px] font-bold mb-1 text-ps-text">
				{__( 'Pixel Scout', 'pixel-scout' )}
			</h1>

			<p className="text-ps-muted text-sm mb-6">
				{__( 'Image similarity search', 'pixel-scout' )}
			</p>

			<StatsBar status={status} />

			<ReindexButton status={status} />

			<div className="grid grid-cols-[1fr_320px] gap-4 mt-4">
				<ImageTable />
				<SearchPreview />
			</div>
		</div>
	)
}
