export default function EmptyState() {
	return (
		<div style={{ textAlign: 'center', padding: '48px 24px', color: 'var(--ps-muted)' }}>
			<div style={{ fontSize: '32px', marginBottom: '12px' }}>🖼</div>

			<div
				style={{ fontSize: '16px', fontWeight: 600, color: 'var(--ps-text)', marginBottom: '8px' }}
			>
				No images indexed yet
			</div>

			<div style={{ fontSize: '14px' }}>Upload images to the Media Library to get started.</div>
		</div>
	)
}
