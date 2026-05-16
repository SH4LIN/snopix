interface Status {
  total: number
  indexed: number
  pending: number
}

interface Props {
  status?: Status
}

export default function StatsBar({ status }: Props) {
  const cards = [
    { label: 'Total Images', value: status?.total ?? '—', color: 'var(--ps-text)' },
    { label: 'Indexed', value: status?.indexed ?? '—', color: 'var(--ps-success)' },
    { label: 'Pending', value: status?.pending ?? '—', color: 'var(--ps-warning)' },
  ]
  return (
    <div
      style={{
        display: 'grid',
        gridTemplateColumns: 'repeat(3, 1fr)',
        gap: '12px',
        marginBottom: '16px',
      }}
    >
      {cards.map(({ label, value, color }) => (
        <div key={label} className="ps-card">
          <div style={{ fontSize: '32px', fontWeight: 700, color }}>
            {value.toLocaleString?.() ?? value}
          </div>
          <div style={{ fontSize: '13px', color: 'var(--ps-muted)', marginTop: '4px' }}>
            {label}
          </div>
        </div>
      ))}
    </div>
  )
}
