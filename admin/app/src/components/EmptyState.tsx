import { __ } from '@wordpress/i18n'

export default function EmptyState() {
	return (
		<div className="text-center py-12 px-6 text-ps-muted">
			<div className="text-[32px] mb-3">🖼</div>
			<div className="text-base font-semibold text-ps-text mb-2">
				{__( 'No images indexed yet', 'pixel-scout' )}
			</div>
			<div className="text-sm">
				{__( 'Upload images to the Media Library to get started.', 'pixel-scout' )}
			</div>
		</div>
	)
}
