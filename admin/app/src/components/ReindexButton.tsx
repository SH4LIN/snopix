import { useStore } from '../store/use-store'
import { useReindex, useProgress } from '../hooks/use-reindex'

interface Status { total: number; indexed: number; pending: number }
interface Props { status?: Status }

export default function ReindexButton({ status }: Props) {
  const isReindexing = useStore((s) => s.isReindexing)
  const setReindexing = useStore((s) => s.setReindexing)
  const { mutate: startReindex } = useReindex()
  const { data: progress } = useProgress(isReindexing)

  const pct = progress && progress.total > 0 ? (progress.done / progress.total) * 100 : 0

  if (progress && progress.done === progress.total && isReindexing && progress.total > 0) {
    setReindexing(false)
  }

  return (
    <div className="ps-card" style={{ marginBottom: '16px' }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '12px' }}>
        <span style={{ fontSize: '14px', color: 'var(--ps-text)' }}>
          {status ? `${status.indexed.toLocaleString()} of ${status.total.toLocaleString()} images indexed` : '—'}
        </span>
        {!isReindexing ? (
          <button className="ps-btn" onClick={() => startReindex()} disabled={!status?.pending}>
            Index Remaining {status?.pending ?? ''} &rarr;
          </button>
        ) : (
          <button className="ps-btn" style={{ background: 'var(--ps-muted)' }} onClick={() => setReindexing(false)}>
            Cancel
          </button>
        )}
      </div>
      <div className="ps-progress">
        <div style={{ height: '100%', width: `${pct}%`, background: 'var(--ps-accent)', borderRadius: 'inherit', transition: 'width 0.4s ease' }} />
      </div>
      {isReindexing && progress && (
        <div style={{ fontSize: '12px', color: 'var(--ps-muted)', marginTop: '6px' }}>
          Indexing&hellip; {progress.done} of {progress.total}
        </div>
      )}
    </div>
  )
}
