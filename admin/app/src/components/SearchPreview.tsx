import { useState, useRef } from 'react'

declare const ps_data: { rest_url: string; nonce: string }

interface SearchResultItem {
	id: number
	url: string
	thumbnail: string
	title: string
	score: number
}

export default function SearchPreview() {
	const [results, setResults] = useState<SearchResultItem[] | null>(null)
	const [loading, setLoading] = useState(false)
	const [error, setError] = useState<string | null>(null)
	const inputRef = useRef<HTMLInputElement>(null)

	async function handleFile(file: File) {
		setLoading(true)
		setError(null)
		setResults(null)
		const fd = new FormData()
		fd.append('file', file)
		try {
			const res = await fetch(`${ps_data.rest_url}search`, {
				method: 'POST',
				headers: { 'X-WP-Nonce': ps_data.nonce },
				body: fd,
			})
			if (!res.ok) throw new Error('Search failed')
			setResults(await res.json())
		} catch {
			setError('Something went wrong. Try a different image.')
		} finally {
			setLoading(false)
		}
	}

	return (
		<div className="ps-card">
			<div
				style={{ fontSize: '14px', fontWeight: 600, marginBottom: '12px', color: 'var(--ps-text)' }}
			>
				Search by Image
			</div>

			<div
				className="ps-drop-zone"
				onClick={() => inputRef.current?.click()}
				onDragOver={(e) => e.preventDefault()}
				onDrop={(e) => {
					e.preventDefault()
					const f = e.dataTransfer.files[0]
					if (f) {
						handleFile(f)
					}
				}}
			>
				<div style={{ fontSize: '13px', color: 'var(--ps-muted)' }}>
					Drop an image to test search
				</div>

				<div style={{ fontSize: '12px', color: 'var(--ps-muted)', marginTop: '4px' }}>
					or click to browse
				</div>

				<input
					ref={inputRef}
					type="file"
					accept="image/*"
					style={{ display: 'none' }}
					onChange={(e) => {
						const f = e.target.files?.[0]
                        if (f) {
                            handleFile(f)
                        }
					}}
				/>
			</div>

			{
				loading && (
					<div style={{ marginTop: '12px', fontSize: '13px', color: 'var(--ps-muted)' }}>
						Searching&hellip;
					</div>
				)
			}

			{
				error && (
					<div style={{ marginTop: '12px', fontSize: '13px', color: 'var(--ps-danger)' }}>
						{error}
					</div>
				)
			}

			{
				results !== null && results.length === 0 && (
					<div style={{ marginTop: '12px', fontSize: '13px', color: 'var(--ps-muted)' }}>
						No similar images found. Try a different image.
					</div>
				)
			}

			{
				results && results.length > 0 && (
					<div style={{ marginTop: '12px', display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '8px' }}>
						{
							results.slice(0, 6).map((r) => (
								<div
									key={r.id}
									style={{
										position: 'relative',
										borderRadius: '8px',
										overflow: 'hidden',
										background: 'var(--ps-surface)',
									}}
								>

									<img
										src={r.thumbnail || r.url}
										alt={r.title}
										style={{ width: '100%', height: '80px', objectFit: 'cover', display: 'block' }}
									/>

									<div
										style={{
											position: 'absolute',
											top: '4px',
											right: '4px',
											background: 'var(--ps-accent)',
											color: '#fff',
											fontSize: '10px',
											borderRadius: '10px',
											padding: '2px 6px',
										}}
									>
										{Math.round(r.score * 100)}%
									</div>
								</div>
							))
						}
					</div>
				)
			}
		</div>
	)
}
