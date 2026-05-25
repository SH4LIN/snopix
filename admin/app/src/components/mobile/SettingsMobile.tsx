import { useState, type ReactNode } from 'react';
import { __ } from '@wordpress/i18n';
import {
	PSSettings,
	useSettings,
	useUpdateSettings,
} from '../../hooks/use-settings';
import Toast from '../Toast';

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
 * Mobile settings screen.
 *
 * Same fields as the desktop Settings tab, surfaced as iOS-style grouped
 * lists. Form state is local; a Save/Discard pair appears at the bottom
 * once the form is dirty (sticky above the bottom tab bar).
 *
 * @return {JSX.Element}
 */
export default function SettingsMobile() {
	const { data, isLoading, isError } = useSettings();
	const { mutate: save, isPending } = useUpdateSettings();

	const [form, setForm] = useState<PSSettings>(DEFAULTS);
	const [serverState, setServerState] = useState<PSSettings>(DEFAULTS);
	const [toast, setToast] = useState<string | null>(null);

	const [syncedData, setSyncedData] = useState<typeof data>();
	if (data && data !== syncedData) {
		const merged = { ...DEFAULTS, ...data };
		setSyncedData(data);
		setForm(merged);
		setServerState(merged);
	}

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

	if (isLoading) {
		return (
			<div className="px-4 pt-5">
				<div className="bg-snopix-bg rounded-card p-5 border border-snopix-border text-snopix-muted text-[13px]">
					{__('Loading settings…', 'snopix')}
				</div>
			</div>
		);
	}

	if (isError) {
		return (
			<div className="px-4 pt-5">
				<div className="bg-snopix-bg rounded-card p-5 border border-snopix-border text-snopix-danger text-[13px]">
					{__('Could not load settings.', 'snopix')}
				</div>
			</div>
		);
	}

	return (
		<>
			<div className="px-4 pt-5 pb-3">
				<div className="text-[24px] font-semibold tracking-[-0.015em] leading-tight">
					{__('Settings', 'snopix')}
				</div>
			</div>

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

			<SectionGroup label={__('Matching', 'snopix')}>
				<SliderRow
					title={__('Match threshold', 'snopix')}
					value={form.match_threshold}
					onChange={(v) => set('match_threshold', v)}
					min={0.5}
					max={1}
					step={0.005}
					leftLabel={__('loose', 'snopix')}
					rightLabel={__('exact only', 'snopix')}
				/>
				<Divider />
				<SliderRow
					title={__('Scan similarity', 'snopix')}
					value={form.duplicate_threshold}
					onChange={(v) => set('duplicate_threshold', v)}
					min={0.8}
					max={1}
					step={0.005}
					leftLabel="0.800"
					rightLabel="1.000"
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

			{dirty && (
				<div className="sticky bottom-[88px] left-0 right-0 z-20 px-4 py-3 bg-snopix-bg/95 backdrop-blur border-t border-snopix-border flex gap-2">
					<button
						type="button"
						className="snopix-btn snopix-btn--ghost snopix-btn--sm flex-1 justify-center"
						onClick={() => setForm(serverState)}
						disabled={isPending}
					>
						{__('Discard', 'snopix')}
					</button>
					<button
						type="button"
						className="snopix-btn snopix-btn--sm flex-1 justify-center"
						onClick={onSave}
						disabled={isPending}
					>
						{isPending ? __('Saving…', 'snopix') : __('Save', 'snopix')}
					</button>
				</div>
			)}

			{toast && <Toast message={toast} onDismiss={() => setToast(null)} />}
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
		<div className="px-4 pb-4">
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
}: {
	title: string;
	value: number;
	onChange: (v: number) => void;
	min: number;
	max: number;
	step: number;
	leftLabel: string;
	rightLabel: string;
}) {
	return (
		<div className="px-3.5 py-3.5">
			<div className="flex items-baseline justify-between mb-2.5">
				<div className="text-[14px] font-medium text-snopix-text">{title}</div>
				<div className="font-mono text-[13px] font-semibold text-snopix-text">
					{value.toFixed(3)}
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
