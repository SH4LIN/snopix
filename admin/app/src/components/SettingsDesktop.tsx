import { type ReactNode } from 'react';
import { __ } from '@wordpress/i18n';
import { useAdvancedOpen, useSettingsForm } from '../hooks/use-settings-form';
import { ratioToPercent } from '../lib/format';
import Toast from './Toast';
import {
	IconChevron,
	IconClock,
	IconGlobe,
	IconInfo,
	IconLayers,
	IconLock,
	IconRefresh,
	IconSearch,
	IconSettings,
} from './icons';

/**
 * Settings tab — endpoint visibility plus an "Advanced" disclosure for
 * developer-facing knobs (rate limiting, similarity thresholds, indexer
 * behaviour, uninstall cleanup).
 *
 * Form state, dirty tracking, and the diff-only save flow are shared with
 * `SettingsMobile` via {@link useSettingsForm}; this component is responsible
 * only for the desktop layout. Similarity thresholds are stored on the
 * backend as 0–1 floats but rendered here as 50–100% so the UI stays
 * approachable for non-developer users.
 *
 * @return {JSX.Element}
 */
export default function Settings() {
	const {
		form,
		set,
		dirty,
		save,
		revert,
		isPending,
		isLoading,
		isError,
		toast,
		dismissToast,
	} = useSettingsForm();
	const [advancedOpen, setAdvancedOpen] = useAdvancedOpen();

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

	const matchPercent = ratioToPercent(form.match_threshold);
	const duplicatePercent = ratioToPercent(form.duplicate_threshold);

	return (
		<>
			<div className="flex items-end justify-between mb-1.5">
				<h1 className="text-[26px] font-semibold tracking-[-0.015em]">
					{__('Settings', 'snopix')}
				</h1>
				{dirty && (
					<div className="flex gap-2">
						<button
							type="button"
							className="snopix-btn snopix-btn--ghost snopix-btn--sm"
							onClick={revert}
							disabled={isPending}
						>
							{__('Discard', 'snopix')}
						</button>
						<button
							type="button"
							className="snopix-btn snopix-btn--sm"
							onClick={save}
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
					'Control who can search your media and tune advanced indexing behaviour.',
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

				<button
					type="button"
					className="snopix-disclosure"
					aria-expanded={advancedOpen}
					onClick={() => setAdvancedOpen(!advancedOpen)}
				>
					<span className="flex items-center gap-3">
						<span className="text-snopix-muted" aria-hidden="true">
							<IconSettings size={16} />
						</span>
						<span>
							<span className="block text-[15px] font-semibold text-snopix-text">
								{__('Advanced settings', 'snopix')}
							</span>
							<span className="block text-[13px] text-snopix-muted mt-0.5">
								{__(
									'Rate limiting, similarity thresholds, indexer behaviour, and uninstall cleanup.',
									'snopix'
								)}
							</span>
						</span>
					</span>
					<span className="snopix-disclosure__chevron" aria-hidden="true">
						<IconChevron size={16} />
					</span>
				</button>

				{advancedOpen && (
					<div className="flex flex-col gap-4">
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
								'Minimum similarity required for an image to surface in search results.',
								'snopix'
							)}
						>
							<SliderRow
								min={50}
								max={100}
								step={1}
								value={matchPercent}
								onChange={(percent) =>
									set('match_threshold', +(percent / 100).toFixed(3))
								}
								valueLabel={`${matchPercent}%`}
								extremes={[
									__('50% · loose', 'snopix'),
									__('100% · exact only', 'snopix'),
								]}
							/>
							<div className="mt-2.5 px-3 py-2.5 bg-snopix-surface rounded-lg text-[12px] text-snopix-muted flex items-start gap-2">
								<IconInfo size={14} />
								<span>
									{__(
										'Format conversions and JPEG re-encodes recover above',
										'snopix'
									)}{' '}
									<strong>95%</strong>.{' '}
									{__(
										'Heavy blur and sub-128 px downscales sit closer to',
										'snopix'
									)}{' '}
									<strong>85%</strong>.
								</span>
							</div>
						</SettingGroup>

						<SettingGroup
							icon={<IconLayers size={16} />}
							title={__('Scan similarity', 'snopix')}
							description={__(
								'Similarity floor used during duplicate scanning. Images cluster as duplicates only when their similarity meets this value.',
								'snopix'
							)}
						>
							<SliderRow
								min={80}
								max={100}
								step={1}
								value={duplicatePercent}
								onChange={(percent) =>
									set(
										'duplicate_threshold',
										+(percent / 100).toFixed(3)
									)
								}
								valueLabel={`${duplicatePercent}%`}
								extremes={[
									__('80% · loose', 'snopix'),
									__('100% · pixel-identical', 'snopix'),
								]}
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
				)}
			</div>

			{toast && <Toast message={toast} onDismiss={dismissToast} />}
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
			type="button"
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
