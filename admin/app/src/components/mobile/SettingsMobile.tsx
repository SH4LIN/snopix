import { type ReactNode } from 'react';
import { __ } from '@wordpress/i18n';
import {
	useAdvancedOpen,
	useSettingsForm,
} from '../../hooks/use-settings-form';
import { ratioToPercent } from '../../lib/format';
import Toast from '../Toast';
import { IconChevron } from '../icons';
import MobileHero from './MobileHero';

/**
 * Mobile settings screen.
 *
 * Surfaces the public endpoint setting up top and keeps every developer-facing
 * knob (rate limit, thresholds, indexer, uninstall) inside a collapsible
 * "Advanced" section so the default view stays approachable. Form state and
 * save semantics are shared with `SettingsDesktop` via {@link useSettingsForm};
 * a Save/Discard pair appears at the bottom once the form is dirty (sticky
 * above the bottom tab bar).
 *
 * Similarity thresholds round-trip as 0–1 floats but render as 50–100% to
 * match the desktop view.
 *
 * @return {JSX.Element}
 */
export default function SettingsMobile() {
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
			<div className="px-[18px] pt-5">
				<div className="bg-snopix-bg rounded-card p-5 border border-snopix-border text-snopix-muted text-[13px]">
					{__('Loading settings…', 'snopix')}
				</div>
			</div>
		);
	}

	if (isError) {
		return (
			<div className="px-[18px] pt-5">
				<div className="bg-snopix-bg rounded-card p-5 border border-snopix-border text-snopix-danger text-[13px]">
					{__('Could not load settings.', 'snopix')}
				</div>
			</div>
		);
	}

	const matchPercent = ratioToPercent(form.match_threshold);
	const duplicatePercent = ratioToPercent(form.duplicate_threshold);

	return (
		<>
			<MobileHero title={__('Settings', 'snopix')} />

			<SectionGroup label={__('Public endpoint', 'snopix')}>
				<RadioRow
					checked={form.search_visibility === 'anyone'}
					onClick={() => set('search_visibility', 'anyone')}
					title={__('Anyone (rate-limited)', 'snopix')}
					hint={`${form.rate_limit} req / 60s`}
				/>
				<Divider />
				<RadioRow
					checked={form.search_visibility === 'logged_in'}
					onClick={() => set('search_visibility', 'logged_in')}
					title={__('Logged-in users', 'snopix')}
					hint={__('Returns 401 to guests.', 'snopix')}
				/>
			</SectionGroup>

			<div className="px-[18px] pb-4">
				<button
					type="button"
					className="snopix-disclosure w-full"
					aria-expanded={advancedOpen}
					onClick={() => setAdvancedOpen(!advancedOpen)}
				>
					<span>
						<span className="block text-[14px] font-semibold text-snopix-text">
							{__('Advanced', 'snopix')}
						</span>
						<span className="block text-[12px] text-snopix-muted mt-0.5">
							{__('Thresholds, indexer, uninstall', 'snopix')}
						</span>
					</span>
					<span className="snopix-disclosure__chevron" aria-hidden="true">
						<IconChevron size={16} />
					</span>
				</button>
			</div>

			{advancedOpen && (
				<>
					<SectionGroup label={__('Matching', 'snopix')}>
						<SliderRow
							title={__('Match threshold', 'snopix')}
							value={matchPercent}
							onChange={(percent) =>
								set('match_threshold', +(percent / 100).toFixed(3))
							}
							min={50}
							max={100}
							step={1}
							leftLabel={__('loose', 'snopix')}
							rightLabel={__('exact only', 'snopix')}
							valueLabel={`${matchPercent}%`}
						/>
						<Divider />
						<SliderRow
							title={__('Scan similarity', 'snopix')}
							value={duplicatePercent}
							onChange={(percent) =>
								set(
									'duplicate_threshold',
									+(percent / 100).toFixed(3)
								)
							}
							min={80}
							max={100}
							step={1}
							leftLabel="80%"
							rightLabel="100%"
							valueLabel={`${duplicatePercent}%`}
						/>
					</SectionGroup>

					<SectionGroup label={__('Indexer', 'snopix')}>
						<NumberRow
							title={__('Batch size', 'snopix')}
							value={form.batch_size}
							onChange={(v) => set('batch_size', v)}
							min={1}
							max={500}
						/>
						<Divider />
						<NumberRow
							title={__('Downscale max', 'snopix')}
							value={form.downscale_max}
							onChange={(v) => set('downscale_max', v)}
							min={64}
							max={4096}
						/>
						<Divider />
						<NumberRow
							title={__('Rate limit', 'snopix')}
							value={form.rate_limit}
							onChange={(v) => set('rate_limit', v)}
							min={1}
							max={1000}
						/>
					</SectionGroup>

					<SectionGroup label={__('Uninstall cleanup', 'snopix')}>
						<SwitchRow
							checked={form.drop_on_uninstall}
							onChange={(v) => set('drop_on_uninstall', v)}
							title={__('Drop wp_snopix_index', 'snopix')}
							hint={__('Removes all fingerprints on uninstall.', 'snopix')}
						/>
						<Divider />
						<SwitchRow
							checked={form.require_consent}
							onChange={(v) => set('require_consent', v)}
							title={__('Require confirmation', 'snopix')}
							hint={__('Show dialog before deleting plugin data.', 'snopix')}
						/>
					</SectionGroup>
				</>
			)}

			{dirty && (
				<div className="sticky bottom-[88px] left-0 right-0 z-20 px-4 py-3 bg-snopix-bg/95 backdrop-blur border-t border-snopix-border flex gap-2">
					<button
						type="button"
						className="snopix-btn snopix-btn--ghost snopix-btn--sm flex-1 justify-center"
						onClick={revert}
						disabled={isPending}
					>
						{__('Discard', 'snopix')}
					</button>
					<button
						type="button"
						className="snopix-btn snopix-btn--sm flex-1 justify-center"
						onClick={save}
						disabled={isPending}
					>
						{isPending ? __('Saving…', 'snopix') : __('Save', 'snopix')}
					</button>
				</div>
			)}

			{toast && <Toast message={toast} onDismiss={dismissToast} />}
		</>
	);
}

function SectionGroup({
	label,
	children,
}: {
	label: string;
	children: ReactNode;
}) {
	return (
		<div className="px-[18px] pb-4">
			<div className="text-[11px] font-medium text-snopix-muted uppercase tracking-[0.05em] px-1 pb-1.5">
				{label}
			</div>
			<div className="bg-snopix-bg rounded-card overflow-hidden border border-snopix-border">
				{children}
			</div>
		</div>
	);
}

function Divider() {
	return <div className="h-px bg-snopix-border ml-3.5" />;
}

function RadioRow({
	checked,
	onClick,
	title,
	hint,
}: {
	checked: boolean;
	onClick: () => void;
	title: string;
	hint: string;
}) {
	return (
		<button
			type="button"
			onClick={onClick}
			className="w-full px-3.5 py-3.5 flex items-center gap-3 text-left bg-transparent border-0"
		>
			<div className="flex-1">
				<div className="text-[14px] text-snopix-text">{title}</div>
				<div className="text-[11px] text-snopix-muted mt-0.5">{hint}</div>
			</div>
			<div
				className={`w-[22px] h-[22px] rounded-full bg-snopix-bg transition-colors ${
					checked
						? 'border-[6px] border-snopix-accent'
						: 'border-[1.5px] border-snopix-border-strong'
				}`}
				aria-hidden="true"
			/>
		</button>
	);
}

function SwitchRow({
	checked,
	onChange,
	title,
	hint,
}: {
	checked: boolean;
	onChange: (v: boolean) => void;
	title: string;
	hint: string;
}) {
	return (
		<label className="flex items-center gap-3 px-3.5 py-3.5 cursor-pointer">
			<div className="flex-1">
				<div className="text-[14px] text-snopix-text">{title}</div>
				<div className="text-[11px] text-snopix-muted mt-0.5">{hint}</div>
			</div>
			<span className="snopix-switch">
				<input
					type="checkbox"
					checked={checked}
					onChange={(e) => onChange(e.target.checked)}
				/>
				<span className="snopix-switch__track" />
			</span>
		</label>
	);
}

function SliderRow({
	title,
	value,
	onChange,
	min,
	max,
	step,
	leftLabel,
	rightLabel,
	valueLabel,
}: {
	title: string;
	value: number;
	onChange: (v: number) => void;
	min: number;
	max: number;
	step: number;
	leftLabel: string;
	rightLabel: string;
	valueLabel: string;
}) {
	return (
		<div className="px-3.5 py-3.5">
			<div className="flex items-baseline justify-between mb-2.5">
				<div className="text-[14px] font-medium text-snopix-text">{title}</div>
				<div className="font-mono text-[13px] font-semibold text-snopix-text">
					{valueLabel}
				</div>
			</div>
			<input
				type="range"
				min={min}
				max={max}
				step={step}
				value={value}
				onChange={(e) => onChange(parseFloat(e.target.value))}
				className="snopix-range w-full"
			/>
			<div className="flex justify-between mt-1.5 text-[11px] text-snopix-muted">
				<span>{leftLabel}</span>
				<span>{rightLabel}</span>
			</div>
		</div>
	);
}

function NumberRow({
	title,
	value,
	onChange,
	min,
	max,
}: {
	title: string;
	value: number;
	onChange: (v: number) => void;
	min: number;
	max: number;
}) {
	return (
		<div className="flex items-center gap-3 px-3.5 py-3.5">
			<div className="flex-1 text-[14px] text-snopix-text">{title}</div>
			<input
				type="number"
				min={min}
				max={max}
				value={value}
				onChange={(e) => {
					const parsed = parseInt(e.target.value, 10);
					if (Number.isFinite(parsed)) {
						onChange(Math.max(min, Math.min(max, parsed)));
					}
				}}
				className="w-24 text-right font-mono text-[13px] py-1.5 px-2 rounded-input border border-snopix-border bg-snopix-bg"
			/>
		</div>
	);
}
