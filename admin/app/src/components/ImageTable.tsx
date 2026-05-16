import { useState } from 'react'
import { useImages } from '../hooks/use-images'
import ImageRow from './ImageRow'

export default function ImageTable() {
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const { data: images, isLoading } = useImages({ page, search })

  return (
    <div className="ps-card">
      <div style={{ marginBottom: '12px' }}>
        <input
          className="ps-input"
          placeholder="Search images…"
          value={search}
          onChange={(e) => {
            setSearch(e.target.value)
            setPage(1)
          }}
          style={{ width: '100%' }}
        />
      </div>
      <table className="ps-table" style={{ width: '100%' }}>
        <thead>
          <tr>
            <th></th>
            <th>File Name</th>
            <th>Dimensions</th>
            <th>Size</th>
            <th>Indexed At</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          {isLoading && (
            <tr>
              <td
                colSpan={6}
                style={{ textAlign: 'center', color: 'var(--ps-muted)', padding: '24px' }}
              >
                Loading&hellip;
              </td>
            </tr>
          )}
          {images?.map((img) => (
            <ImageRow key={img.attachment_id} image={img} />
          ))}
          {!isLoading && images?.length === 0 && (
            <tr>
              <td
                colSpan={6}
                style={{ textAlign: 'center', color: 'var(--ps-muted)', padding: '24px' }}
              >
                No images found
              </td>
            </tr>
          )}
        </tbody>
      </table>
      <div
        style={{
          display: 'flex',
          justifyContent: 'space-between',
          alignItems: 'center',
          marginTop: '12px',
          fontSize: '13px',
          color: 'var(--ps-muted)',
        }}
      >
        <span>Page {page}</span>
        <div style={{ display: 'flex', gap: '8px' }}>
          <button
            onClick={() => setPage((p) => Math.max(1, p - 1))}
            disabled={page === 1}
            className="ps-btn"
            style={{ padding: '4px 10px', fontSize: '13px' }}
          >
            &larr;
          </button>
          <button
            onClick={() => setPage((p) => p + 1)}
            disabled={!images || images.length < 25}
            className="ps-btn"
            style={{ padding: '4px 10px', fontSize: '13px' }}
          >
            &rarr;
          </button>
        </div>
      </div>
    </div>
  )
}
