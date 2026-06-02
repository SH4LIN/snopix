/**
 * Snopix frontend search widget.
 *
 * Implements the upload -> scan -> results flow against
 * `POST /wp-json/snopix/v1/search`. Self-contained: all styles are Tailwind
 * utilities scoped under `.snopix-widget`, so dropping the widget into any
 * host theme cannot leak utility classes back into surrounding markup.
 *
 * Variants:
 *   - `card`   default chrome with a header strip + powered-by attribution
 *   - `inline` no header, horizontal drop row, designed to live inside an
 *              article body
 *   - `narrow` mobile-width treatment with single-column results grid
 */
import { useRef, useState } from 'react'
import { IconEmpty, IconMark, IconReset, IconUpload } from './icons'

export type WidgetVariant = 'card' | 'inline' | 'narrow'

type SearchResult = {
	id: number
	url: string
	thumbnail: string
	title: string
	score: number
	attachment_url: string
}

type Phase = 'idle' | 'scanning' | 'results' | 'empty' | 'error'

type Probe = {
	name: string
	sizeKb: number
	previewUrl: string
}

type Props = {
	variant: WidgetVariant
	title: string
	maxResults: number
	restUrl: string
	nonce: string
}

const ACCEPT = 'image/jpeg,image/png,image/gif,image/webp'
const MAX_BYTES = 8 * 1024 * 1024

function rootClass(variant: WidgetVariant): string {
	const base = 'snopix-widget'
	if (variant === 'inline') return `${base} sx--inline`
	if (variant === 'narrow') return `${base} sx--narrow`
	return base
}

function safeUrl(value: string): string {
	try {
		return new URL(value, window.location.origin).href
	} catch {
		return '#'
	}
}

export default function SnopixWidget({
	variant,
	title,
	maxResults,
	restUrl,
	nonce,
}: Props) {
	const [phase, setPhase] = useState<Phase>('idle')
	const [over, setOver] = useState(false)
	const [probe, setProbe] = useState<Probe | null>(null)
	const [results, setResults] = useState<SearchResult[]>([])
	const [errorMessage, setErrorMessage] = useState('')
	const fileInputRef = useRef<HTMLInputElement | null>(null)

	const showHeader = variant !== 'inline'
	const resultsWrapColumns =
		variant === 'card'
			? 'grid-cols-[220px_minmax(0,1fr)]'
			: 'grid-cols-1'
	const resultsGridColumns =
		variant === 'narrow'
			? 'grid-cols-2'
			: 'grid-cols-[repeat(auto-fill,minmax(140px,1fr))]'

	function reset() {
		setPhase('idle')
		setResults([])
		setErrorMessage('')
		if (probe) {
			URL.revokeObjectURL(probe.previewUrl)
		}
		setProbe(null)
		if (fileInputRef.current) {
			fileInputRef.current.value = ''
		}
	}

	async function handleFile(file: File) {
		if (file.size > MAX_BYTES) {
			setErrorMessage('Image is too large. Max 8 MB.')
			setPhase('error')
			return
		}

		const preview = URL.createObjectURL(file)
		if (probe) {
			URL.revokeObjectURL(probe.previewUrl)
		}
		setProbe({
			name: file.name,
			sizeKb: Math.round(file.size / 1024),
			previewUrl: preview,
		})
		setPhase('scanning')
		setErrorMessage('')

		const body = new FormData()
		body.append('file', file)

		try {
			const res = await fetch(`${restUrl}search`, {
				method: 'POST',
				headers: { 'X-WP-Nonce': nonce },
				body,
			})
			const data: unknown = await res.json().catch(() => null)

			if (!res.ok) {
				const message =
					data && typeof data === 'object' && data !== null && 'message' in data
						? String((data as { message: unknown }).message)
						: 'Something went wrong. Try a different image.'
				setErrorMessage(message)
				setPhase('error')
				return
			}

			if (!Array.isArray(data)) {
				setErrorMessage('Unexpected response from the server.')
				setPhase('error')
				return
			}

			const capped = (data as SearchResult[]).slice(0, maxResults)
			setResults(capped)
			setPhase(capped.length === 0 ? 'empty' : 'results')
		} catch {
			setErrorMessage('Something went wrong. Try a different image.')
			setPhase('error')
		}
	}

	function onPick(event: React.ChangeEvent<HTMLInputElement>) {
		const file = event.target.files?.[0]
		if (file) handleFile(file)
	}

	function onDrop(event: React.DragEvent<HTMLDivElement>) {
		event.preventDefault()
		setOver(false)
		const file = event.dataTransfer.files?.[0]
		if (file) handleFile(file)
	}

	const dropZone = (
		<div
			className={[
				'flex flex-col items-center justify-center gap-3.5 text-center cursor-pointer select-none',
				variant === 'inline'
					? 'p-[22px] flex-row gap-4 text-left justify-start rounded-none border-0 border-b border-snopix-border'
					: 'mx-[18px] my-[18px] py-14 px-6 border-[1.5px] border-dashed border-snopix-border-strong rounded-card bg-snopix-surface',
				over ? 'border-snopix-accent bg-snopix-accent-soft' : '',
				'transition-colors duration-200 ease-out',
			].join(' ')}
			data-over={over}
			onClick={() => fileInputRef.current?.click()}
			onDragOver={(e) => {
				e.preventDefault()
				setOver(true)
			}}
			onDragLeave={() => setOver(false)}
			onDrop={onDrop}
		>
			<div
				className={[
					'rounded-full grid place-items-center bg-snopix-bg border border-snopix-border text-snopix-accent shadow-[0_6px_16px_rgba(0,113,227,0.10)] transition-transform duration-200',
					variant === 'inline' ? 'w-10 h-10' : 'w-14 h-14',
				].join(' ')}
			>
				<IconUpload size={variant === 'inline' ? 18 : 22} />
			</div>
			<div>
				<div
					className={[
						'font-medium text-snopix-text tracking-[-0.01em]',
						variant === 'inline' ? 'text-sm' : 'text-base',
					].join(' ')}
				>
					Drop an image to search
				</div>
				<div
					className={[
						'text-snopix-muted',
						variant === 'inline' ? 'text-xs' : 'text-[13px]',
					].join(' ')}
				>
					JPEG · PNG · WebP · GIF · max 8 MB
				</div>
			</div>
			<button
				type="button"
				className={[
					'inline-flex items-center gap-1.5 px-4 py-2 bg-snopix-bg text-snopix-text border border-snopix-border-strong rounded-input text-[13px] font-medium transition-colors duration-200 hover:border-snopix-accent hover:text-snopix-accent',
					variant === 'inline' ? 'ml-auto mt-0' : 'mt-1',
				].join(' ')}
				onClick={(e) => {
					e.stopPropagation()
					fileInputRef.current?.click()
				}}
			>
				<IconUpload size={14} /> Choose file
			</button>
			<input
				ref={fileInputRef}
				type="file"
				accept={ACCEPT}
				hidden
				onChange={onPick}
			/>
		</div>
	)

	const resultsPanel = probe && (
		<div className={`grid gap-6 p-[18px] ${resultsWrapColumns} ${variant === 'narrow' ? 'gap-4' : ''}`}>
			<div
				className={[
					'flex flex-col gap-2.5',
					variant === 'narrow' ? 'flex-row items-center gap-3.5' : '',
				].join(' ')}
			>
				<div
					className={[
						'rounded-input overflow-hidden bg-snopix-surface border border-snopix-border',
						variant === 'narrow' ? 'w-[72px] h-[72px] shrink-0' : 'w-full aspect-square',
					].join(' ')}
				>
					<img
						src={probe.previewUrl}
						alt={probe.name}
						className="block w-full h-full object-cover"
					/>
				</div>
				<div className="min-w-0">
					<div className="text-[13px] font-medium text-snopix-text break-all leading-snug">
						{probe.name}
					</div>
					<div className="font-mono text-[11px] text-snopix-muted">
						probe · {probe.sizeKb} KB
					</div>
					{phase === 'scanning' ? (
						<div className="sx-progress">
							<div className="sx-progress__fill" />
						</div>
					) : (
						<button
							type="button"
							onClick={reset}
							className="inline-flex items-center gap-1 mt-1 text-xs text-snopix-accent hover:text-snopix-accent-deep hover:underline"
						>
							<IconReset size={12} /> New search
						</button>
					)}
				</div>
			</div>

			<div className="min-w-0">
				<div className="flex items-center justify-between gap-4 mb-3">
					<div className="text-sm font-semibold text-snopix-text">
						{phase === 'scanning' && 'Searching…'}
						{phase === 'results' && `${results.length} match${results.length === 1 ? '' : 'es'}`}
						{phase === 'empty' && 'No matches'}
						{phase === 'error' && 'Search failed'}
					</div>
				</div>

				{phase === 'scanning' && (
					<div className={`grid gap-2.5 ${resultsGridColumns}`}>
						{Array.from({ length: Math.min(6, maxResults) }).map((_, i) => (
							<div
								key={i}
								className="flex flex-col rounded-input overflow-hidden bg-snopix-bg border border-snopix-border"
							>
								<div className="aspect-square sx-skel" />
								<div className="px-2.5 py-2 border-t border-snopix-border">
									<div className="sx-skel h-2 rounded-sm w-4/5" />
									<div className="mt-2 h-[3px] bg-snopix-border rounded-sm" />
								</div>
							</div>
						))}
					</div>
				)}

				{phase === 'results' && (
					<div className={`grid gap-2.5 ${resultsGridColumns}`}>
						{results.map((r) => (
							<a
								key={r.id}
								href={safeUrl(r.attachment_url || r.url)}
								target="_blank"
								rel="noopener noreferrer"
								className="flex flex-col rounded-input overflow-hidden bg-snopix-bg border border-snopix-border transition-all duration-200 hover:-translate-y-0.5 hover:border-snopix-border-strong hover:shadow-[0_6px_16px_rgba(0,0,0,0.06)] relative"
							>
								<div className="aspect-square bg-snopix-surface relative">
									<img
										src={safeUrl(r.thumbnail || r.url)}
										alt={r.title || ''}
										loading="lazy"
										className="w-full h-full object-cover"
									/>
									<div className="sx-score absolute top-2 right-2 px-2 py-0.5 rounded-pill font-mono text-[11px] font-semibold bg-white/90 text-snopix-accent-deep">
										{r.score.toFixed(2)}
									</div>
								</div>
								<div className="px-2.5 py-2 border-t border-snopix-border">
									<div className="text-xs font-medium overflow-hidden text-ellipsis whitespace-nowrap">
										{r.title || '—'}
									</div>
									<div className="mt-1.5 h-[3px] bg-snopix-border rounded-sm overflow-hidden">
										<div
											className="h-full bg-snopix-accent"
											style={{ width: `${Math.round(r.score * 100)}%` }}
										/>
									</div>
								</div>
							</a>
						))}
					</div>
				)}

				{phase === 'empty' && (
					<div className="py-12 px-6 text-center text-snopix-muted">
						<div className="w-14 h-14 rounded-full bg-snopix-surface inline-grid place-items-center text-snopix-border-strong mb-4">
							<IconEmpty size={22} />
						</div>
						<div className="text-base text-snopix-text font-medium mb-1">
							No visually similar images
						</div>
						<div>Try a sharper crop, or upload a different reference.</div>
					</div>
				)}

				{phase === 'error' && (
					<div className="py-10 px-6 text-center text-snopix-danger text-sm">
						{errorMessage}
					</div>
				)}
			</div>
		</div>
	)

	return (
		<div className={rootClass(variant)}>
			<div className="bg-snopix-bg border border-snopix-border rounded-card shadow-card overflow-hidden">
				{showHeader && (
					<div className="flex items-center justify-between px-[18px] py-3.5 border-b border-snopix-border">
						<div className="flex items-center gap-2.5 text-sm font-semibold text-snopix-text tracking-[-0.005em]">
							<IconMark size={18} />
							{title}
						</div>
						<div className="font-mono text-[11px] text-snopix-muted tracking-wider">
							powered by snopix
						</div>
					</div>
				)}

				{phase === 'idle' && dropZone}
				{phase !== 'idle' && resultsPanel}
			</div>
		</div>
	)
}
