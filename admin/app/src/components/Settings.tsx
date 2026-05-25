import { useEffect, useState, type ReactNode } from 'react';
import { __ } from '@wordpress/i18n';
import {
	useSettings,
	useUpdateSettings,
	type PSSettings,
} from '../hooks/use-settings';
import Toast from './Toast';
import {
	IconClock,
	IconGlobe,
	IconInfo,
	IconLayers,
	IconLock,
	IconRefresh,
	IconSearch,
} from './icons';

/**
 * Default form payload used when the server-side query is still loading or
 * the option row is missing fields. Keep in sync with the PHP `Settings`
 * sanitizer defaults.
 */
const DEFAULTS: PSSettings = {
	search_visibility: 'anyone',
	rate_limit: 10,
	match_threshold: 0.85,
	batch_size: 25,
	downscale_max: 1024,
	duplicate_threshold: 0.95,
	drop_on_uninstall: true,
	require_consent: false,
};

/**
 * Settings tab — endpoint visibility, rate limiting, similarity thresholds,
 * indexer batch behaviour, and uninstall cleanup.
 *
 * Holds the form state locally and exposes a Save/Discard pair in the page
 * header once the form is dirty. Saves persist via
 * `POST /wp-json/snopix/v1/settings` and refresh the cached state on success.
 *
 * @return {JSX.Element}
 */
export default function Settings() {
	const { data, isLoading, isError } = useSettings();
	const { mutate: save, isPending } = useUpdateSettings();

	const [form, setForm] = useState<PSSettings>(DEFAULTS);
	const [serverState, setServerState] = useState<PSSettings>(DEFAULTS);
	const [toast, setToast] = useState<string | null>(null);

	useEffect(() => {
		if (data) {
			const merged = { ...DEFAULTS, ...data };
			setForm(merged);
			setServerState(merged);
		}
	}, [data]);

	const set = <K extends keyof PSSettings>(k: K, v: PSSettings[K]) =>
		setForm((p) => ({ ...p, [k]: v }));

	const dirty = JSON.stringify(form) !== JSON.stringify(serverState);

	function onSave() {
		save(form, {
			onSuccess: (next) => {
				const merged = { ...DEFAULTS, ...next };
				setServerState(merged);
				setForm(merged);
				setToast(__('Settings saved', 'snopix'));
			},
			onError: () => setToast(__('Could not save. Try again.', 'snopix')),
		});
	}

	function revert() {
		setForm(serverState);
	}

	if (isLoading) {
		return (
			<div className="snopix-card snopix-card--pad text-snopix-muted">
				{__('Loading settings…', 'snopix')}
			</div>
		);
	}

	if (isError) {
		return (
			<div className="snopix-card snopix-card--pad text-snopix-danger">
				{__('Could not load settings.', 'snopix')}
			</div>
		);
	}

	return (
		<>
			<div className="flex items-end justify-between mb-1.5">
				<h1 className="text-[26px] font-semibold tracking-[-0.015em]">
					{__('Settings', 'snopix')}
				</h1>
				{dirty && (
					<div className="flex gap-2">
						<button
							className="snopix-btn snopix-btn--ghost snopix-btn--sm"
							onClick={revert}
							disabled={isPending}
						>
							{__('Discard', 'snopix')}
						</button>
						<button
							className="snopix-btn snopix-btn--sm"
							onClick={onSave}
							disabled={isPending}
						>
							{isPending
								? __('Saving…', 'snopix')
								: __('Save changes', 'snopix')}
						</button>
					</div>
				)}
			</div>
			<p className="text-[14px] text-snopix-muted mb-7">
				{__(
					'Endpoint visibility, indexing behaviour, and uninstall cleanup.',
					'snopix'
				)}
			</p>

			<div className="flex flex-col gap-4">
				<SettingGroup
					icon={<IconGlobe size={16} />}
					title={__('Public search endpoint', 'snopix')}
					description={__(
						'Who can POST images to /wp-json/snopix/v1/search and to the [snopix_search] shortcode.',
						'snopix'
					)}
				>
					<RadioRow
						checked={form.search_visibility === 'anyone'}
						onClick={() => set('search_visibility', 'anyone')}
						title={__('Anyone (rate-limited)', 'snopix')}
						hint={__(
							'Anonymous visitors can search the front-end widget. Rate-limited per IP.',
							'snopix'
						)}
					/>
					<Divider />
					<RadioRow
						checked={form.search_visibility === 'logged_in'}
						onClick={() => set('search_visibility', 'logged_in')}
						title={__('Logged-in users only', 'snopix')}
						hint={__(
							'Endpoint returns 401 to unauthenticated requests. Shortcode hides the widget.',
							'snopix'
						)}
					/>
				</SettingGroup>

				<SettingGroup
					icon={<IconClock size={16} />}
					title={__('Rate limit', 'snopix')}
					description={__(
						'Cap on POST /search per requester. Applies only when the endpoint is public.',
						'snopix'
					)}
				>
					<SliderRow
						min={1}
						max={60}
						step={1}
						value={form.rate_limit}
						onChange={(v) => set('rate_limit', v)}
						valueLabel={`${form.rate_limit} req / 60 s`}
						extremes={['1 / 60 s', '60 / 60 s']}
						disabled={form.search_visibility !== 'anyone'}
					/>
				</SettingGroup>

				<SettingGroup
					icon={<IconSearch size={16} />}
					title={__('Match threshold', 'snopix')}
					description={__(
						'Minimum composite score for an image to surface in search results.',
						'snopix'
					)}
				>
					<SliderRow
						min={0.5}
						max={1.0}
						step={0.005}
						value={form.match_threshold}
						onChange={(v) =>
							set('match_threshold', parseFloat(v.toFixed(3)))
						}
						valueLabel={form.match_threshold.toFixed(3)}
						extremes={['0.500 · loose', '1.000 · exact only']}
					/>
					<div className="mt-2.5 px-3 py-2.5 bg-snopix-surface rounded-lg text-[12px] text-snopix-muted flex items-start gap-2">
						<IconInfo size={14} />
						<span>
							{__(
								'Format conversions and JPEG re-encodes recover above',
								'snopix'
							)}{' '}
							<strong>0.95</strong>.{' '}
							{__(
								'Heavy blur and sub-128 px downscales sit closer to',
								'snopix'
							)}{' '}
							<strong>0.85</strong>.
						</span>
					</div>
				</SettingGroup>

				<SettingGroup
					icon={<IconLayers size={16} />}
					title={__('Scan similarity', 'snopix')}
					description={__(
						'Similarity floor used during scanning. Images are clustered as duplicates only if their similarity is at or above this value.',
						'snopix'
					)}
				>
					<SliderRow
						min={0.8}
						max={1.0}
						step={0.005}
						value={form.duplicate_threshold}
						onChange={(v) =>
							set('duplicate_threshold', parseFloat(v.toFixed(3)))
						}
						valueLabel={form.duplicate_threshold.toFixed(3)}
						extremes={['0.800 · loose', '1.000 · pixel-identical']}
					/>
				</SettingGroup>

				<SettingGroup
					icon={<IconRefresh size={16} />}
					title={__('Indexer', 'snopix')}
					description={__(
						'How the background fingerprinter processes your library.',
						'snopix'
					)}
				>
					<RowField
						label={__('Batch size', 'snopix')}
						hint={__(
							'Attachments fingerprinted per WP-Cron tick. Raise for speed; lower if PHP memory is tight.',
							'snopix'
						)}
					>
						<NumberStepper
							value={form.batch_size}
							min={5}
							max={200}
							step={5}
							onChange={(v) => set('batch_size', v)}
							suffix={__('rows', 'snopix')}
						/>
					</RowField>
					<Divider />
					<RowField
						label={__('Pre-downscale max edge', 'snopix')}
						hint={__(
							'Probe images larger than this are downscaled before fingerprinting to keep search latency bounded.',
							'snopix'
						)}
					>
						<NumberStepper
							value={form.downscale_max}
							min={256}
							max={4096}
							step={128}
							onChange={(v) => set('downscale_max', v)}
							suffix={__('px', 'snopix')}
						/>
					</RowField>
					<Divider />
					<RowField
						label={__('Supported MIME types', 'snopix')}
						hint={__(
							'Read-only. The indexer rejects anything not in this list at upload and at the search endpoint.',
							'snopix'
						)}
					>
						<div className="flex gap-1.5 flex-wrap justify-end">
							{[
								'image/jpeg',
								'image/png',
								'image/gif',
								'image/webp',
								'image/bmp',
							].map((m) => (
								<span
									key={m}
									className="snopix-pill snopix-pill--neutral snopix-mono"
								>
									{m}
								</span>
							))}
						</div>
					</RowField>
				</SettingGroup>

				<SettingGroup
					icon={<IconLock size={16} />}
					title={__('Uninstall cleanup', 'snopix')}
					description={__(
						'What happens when the plugin is deleted from Plugins → Installed Plugins.',
						'snopix'
					)}
				>
					<ToggleRow
						checked={form.drop_on_uninstall}
						onChange={(v) => set('drop_on_uninstall', v)}
						title={__(
							'Drop the wp_snopix_index table on uninstall',
							'snopix'
						)}
						hint={__(
							'Recommended. Removes all fingerprints and every Snopix option / transient.',
							'snopix'
						)}
					/>
					<Divider />
					<ToggleRow
						checked={form.require_consent}
						onChange={(v) => set('require_consent', v)}
						title={__(
							'Require admin confirmation before uninstall',
							'snopix'
						)}
						hint={__(
							'Adds a confirmation dialog on the Plugins screen before the table is dropped.',
							'snopix'
						)}
					/>
				</SettingGroup>
			</div>

			{toast && <Toast message={toast} onDismiss={() => setToast(null)} />}
		</>
	);
}

interface SettingGroupProps {
	icon: ReactNode;
	title: string;
	description: string;
	children: ReactNode;
}

function SettingGroup({
	icon,
	title,
	description,
	children,
}: SettingGroupProps) {
	return (
		<div className="snopix-card grid grid-cols-1 md:grid-cols-[280px_1fr]">
			<div className="p-6 border-b md:border-b-0 md:border-r border-snopix-border">
				<div className="flex items-center gap-2 text-snopix-muted mb-1.5">
					{icon}
					<span className="text-[11px] font-semibold uppercase tracking-[0.05em]">
						{__('Section', 'snopix')}
					</span>
				</div>
				<div className="text-[15px] font-semibold mb-1.5">{title}</div>
				<div className="text-[13px] text-snopix-muted leading-[1.55]">
					{description}
				</div>
			</div>
			<div className="px-6 py-2">{children}</div>
		</div>
	);
}

interface RadioRowProps {
	checked: boolean;
	onClick: () => void;
	title: string;
	hint: string;
}

function RadioRow({ checked, onClick, title, hint }: RadioRowProps) {
	return (
		<button
			onClick={onClick}
			className="flex items-start gap-3 py-4 w-full text-left bg-transparent"
		>
			<span
				className="snopix-radio mt-0.5"
				data-checked={checked ? 'true' : 'false'}
			/>
			<span>
				<span className="text-sm font-medium text-snopix-text block">
					{title}
				</span>
				<span className="text-[13px] text-snopix-muted mt-0.5 leading-[1.5] block">
					{hint}
				</span>
			</span>
		</button>
	);
}

interface ToggleRowProps {
	checked: boolean;
	onChange: (v: boolean) => void;
	title: string;
	hint: string;
}

function ToggleRow({ checked, onChange, title, hint }: ToggleRowProps) {
	return (
		<div className="flex items-start justify-between gap-4 py-4">
			<div>
				<div className="text-sm font-medium">{title}</div>
				<div className="text-[13px] text-snopix-muted mt-0.5 leading-[1.5]">
					{hint}
				</div>
			</div>
			<label className="snopix-switch">
				<input
					type="checkbox"
					checked={checked}
					onChange={(e) => onChange(e.target.checked)}
				/>
				<span className="snopix-switch__track" />
			</label>
		</div>
	);
}

interface SliderRowProps {
	min: number;
	max: number;
	step: number;
	value: number;
	onChange: (v: number) => void;
	valueLabel: string;
	extremes: [string, string];
	disabled?: boolean;
}

function SliderRow({
	min,
	max,
	step,
	value,
	onChange,
	valueLabel,
	extremes,
	disabled,
}: SliderRowProps) {
	return (
		<div
			className={`py-4 ${disabled ? 'opacity-50 pointer-events-none' : ''}`}
		>
			<div className="flex justify-between items-baseline mb-2.5">
				<span className="text-[13px] text-snopix-muted">
					{extremes[0]}
				</span>
				<span className="snopix-mono text-sm font-semibold text-snopix-text">
					{valueLabel}
				</span>
				<span className="text-[13px] text-snopix-muted">
					{extremes[1]}
				</span>
			</div>
			<input
				className="snopix-range"
				type="range"
				min={min}
				max={max}
				step={step}
				value={value}
				onChange={(e) => onChange(parseFloat(e.target.value))}
				disabled={disabled}
			/>
		</div>
	);
}

interface RowFieldProps {
	label: string;
	hint: string;
	children: ReactNode;
}

function RowField({ label, hint, children }: RowFieldProps) {
	return (
		<div className="flex items-start justify-between gap-4 py-4">
			<div className="max-w-[420px]">
				<div className="text-sm font-medium">{label}</div>
				<div className="text-[13px] text-snopix-muted mt-0.5 leading-[1.5]">
					{hint}
				</div>
			</div>
			<div>{children}</div>
		</div>
	);
}

interface NumberStepperProps {
	value: number;
	min: number;
	max: number;
	step: number;
	onChange: (v: number) => void;
	suffix: string;
}

function NumberStepper({
	value,
	min,
	max,
	step,
	onChange,
	suffix,
}: NumberStepperProps) {
	return (
		<div className="inline-flex items-center border border-snopix-border rounded-[var(--snopix-radius-input)] overflow-hidden bg-white">
			<button
				type="button"
				className="px-3 py-2 text-snopix-muted"
				onClick={() => onChange(Math.max(min, value - step))}
				disabled={value <= min}
			>
				−
			</button>
			<div className="py-2 min-w-[100px] text-center snopix-mono text-[13px] font-semibold border-l border-r border-snopix-border">
				{value}{' '}
				<span className="text-snopix-muted font-medium">{suffix}</span>
			</div>
			<button
				type="button"
				className="px-3 py-2 text-snopix-muted"
				onClick={() => onChange(Math.min(max, value + step))}
				disabled={value >= max}
			>
				+
			</button>
		</div>
	);
}

function Divider() {
	return <div className="h-px bg-snopix-border" />;
}
