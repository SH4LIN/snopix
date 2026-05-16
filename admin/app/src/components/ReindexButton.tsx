import { useStore } from '../store/use-store'
import { useReindex, useIndexingProgress } from '../hooks/use-reindex'

interface Status {
  total: number
  indexed: number
  pending: number
}
interface Props {
  status?: Status
}

export default function ReindexButton({ status }: Props) {
  const { indexingState, setIndexingState } = useStore()
  const { mutate: startReindex, isPending } = useReindex()
  const progress = useIndexingProgress()

  const isIdle = indexingState === 'idle'
  const isRunning = indexingState === 'running'
  const isDone = indexingState === 'done'
  const isStalled = indexingState === 'stalled'

  const pct = progress && progress.total > 0 ? (progress.done / progress.total) * 100 : 0

  return (
    <div className="ps-card" style={{ marginBottom: '16px' }}>
      <div
        style={{
          display: 'flex',
          justifyContent: 'space-between',
          alignItems: 'center',
          marginBottom: '12px',
        }}
      >
        <span style={{ fontSize: '14px', color: 'var(--ps-text)' }}>
          {status
            ? `${status.indexed.toLocaleString()} of ${status.total.toLocaleString()} images indexed`
            : '—'}
        </span>

        {isIdle && (
          <button
            className="ps-btn"
            onClick={() => startReindex()}
            disabled={isPending || !status?.pending}
          >
            Index Remaining {status?.pending ?? ''} &rarr;
          </button>
        )}

        {isRunning && (
          <button
            className="ps-btn"
            style={{ background: 'var(--ps-muted)' }}
            onClick={() => setIndexingState('idle')}
          >
            Cancel
          </button>
        )}
      </div>

      {(isRunning || isDone) && (
        <div className="ps-progress">
          <div
            style={{
              height: '100%',
              width: `${isDone ? 100 : pct}%`,
              background: isDone ? 'var(--ps-success)' : 'var(--ps-accent)',
              borderRadius: 'inherit',
              transition: 'width 0.4s ease',
            }}
          />
        </div>
      )}

      {isRunning && progress && (
        <div style={{ fontSize: '12px', color: 'var(--ps-muted)', marginTop: '6px' }}>
          Indexing&hellip; {progress.done} of {progress.total}
        </div>
      )}

      {isDone && (
        <div style={{ fontSize: '12px', color: 'var(--ps-success)', marginTop: '6px' }}>
          ✓ Indexing complete
        </div>
      )}

      {isStalled && (
        <div style={{ fontSize: '12px', color: 'var(--ps-danger)', marginTop: '6px' }}>
          ✗ Indexing stalled — check server cron
        </div>
      )}
    </div>
  )
}
