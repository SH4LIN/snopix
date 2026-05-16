import StatsBar from './StatsBar'
import ReindexButton from './ReindexButton'
import ImageTable from './ImageTable'
import SearchPreview from './SearchPreview'
import { useIndexStatus } from '../hooks/use-index-status'

export default function Dashboard() {
  const { data: status } = useIndexStatus()

  return (
    <div id="pixel-scout-app" style={{ padding: '24px', maxWidth: '1200px' }}>
      <h1
        style={{ fontSize: '28px', fontWeight: 700, marginBottom: '4px', color: 'var(--ps-text)' }}
      >
        Pixel Scout
      </h1>
      <p style={{ color: 'var(--ps-muted)', marginBottom: '24px', fontSize: '14px' }}>
        Image similarity search
      </p>
      <StatsBar status={status} />
      <ReindexButton status={status} />
      <div
        style={{
          display: 'grid',
          gridTemplateColumns: '1fr 320px',
          gap: '16px',
          marginTop: '16px',
        }}
      >
        <ImageTable />
        <SearchPreview />
      </div>
    </div>
  )
}
