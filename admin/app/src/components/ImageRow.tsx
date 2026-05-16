interface ImageData {
  attachment_id: number
  mime_type: string
  file_size: number
  width: number
  height: number
  indexed_at: string
  phash: string
  title?: string
  filename?: string
  thumbnail_url?: string
}

interface Props {
  image: ImageData
}

function formatBytes(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / 1024 / 1024).toFixed(1)} MB`
}

export default function ImageRow({ image }: Props) {
  const editUrl = `/wp-admin/post.php?post=${image.attachment_id}&action=edit`
  const isIndexed = !!image.phash
  const pillClass = isIndexed ? 'ps-pill ps-pill--indexed' : 'ps-pill ps-pill--pending'
  const label = isIndexed ? 'Indexed' : 'Pending'
  const date = image.indexed_at ? new Date(image.indexed_at).toLocaleDateString() : '—'
  const displayName = image.filename || image.title || `ID ${image.attachment_id}`

  return (
    <tr onClick={() => window.open(editUrl, '_blank')} style={{ cursor: 'pointer' }}>
      <td>
        {image.thumbnail_url ? (
          <img
            src={image.thumbnail_url}
            alt={displayName}
            style={{
              width: '48px',
              height: '48px',
              objectFit: 'cover',
              borderRadius: '6px',
              display: 'block',
            }}
          />
        ) : (
          <div
            style={{
              width: '48px',
              height: '48px',
              background: 'var(--ps-surface)',
              borderRadius: '6px',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              fontSize: '10px',
              color: 'var(--ps-muted)',
            }}
          >
            IMG
          </div>
        )}
      </td>
      <td style={{ fontSize: '13px' }}>
        {displayName}
        <br />
        <span style={{ color: 'var(--ps-muted)', fontSize: '11px' }}>{image.mime_type}</span>
      </td>
      <td style={{ fontSize: '13px' }}>
        {image.width} &times; {image.height}
      </td>
      <td style={{ fontSize: '13px' }}>{formatBytes(image.file_size)}</td>
      <td style={{ fontSize: '13px' }}>{date}</td>
      <td>
        <span className={pillClass}>{label}</span>
      </td>
    </tr>
  )
}
