import { useEffect, useState } from 'react';
import { __ } from '@wordpress/i18n';
import {
	useSettings,
	useUpdateSettings,
	type PSSettings,
} from './use-settings';

/**
 * Canonical defaults used while the server payload is loading or when a stored
 * option row is missing fields. Kept in sync with `Settings::defaults()` in
 * PHP so reverting/loading never produces an out-of-range form value.
 */
export const SETTINGS_DEFAULTS: PSSettings = {
	search_visibility: 'anyone',
	rate_limit: 10,
	match_threshold: 0.85,
	batch_size: 25,
	downscale_max: 1024,
	duplicate_threshold: 0.95,
	drop_on_uninstall: true,
};

const ADVANCED_OPEN_KEY = 'snopix:settings:advanced-open';

/**
 * Shape returned by {@link useSettingsForm}.
 */
export interface SettingsForm {
	/** Current (possibly dirty) form values rendered by the UI. */
	form: PSSettings;
	/** Update a single field. */
	set: <K extends keyof PSSettings>(key: K, value: PSSettings[K]) => void;
	/** True when `form` differs from the last server-confirmed snapshot. */
	dirty: boolean;
	/** Persist the diff. No-op when not dirty or a save is already in flight. */
	save: () => void;
	/** Roll the form back to the last server-confirmed snapshot. */
	revert: () => void;
	/** Whether a save mutation is currently in flight. */
	isPending: boolean;
	/** Whether the initial GET /settings is still loading. */
	isLoading: boolean;
	/** Whether the initial GET /settings failed. */
	isError: boolean;
	/** Current toast message (or null). */
	toast: string | null;
	/** Dismiss the active toast. */
	dismissToast: () => void;
}

/**
 * Owns the entire settings form lifecycle: server hydration, dirty tracking,
 * diff-only save, revert, and toast feedback. Desktop and mobile Settings
 * screens both consume this hook so save behaviour stays identical across
 * viewports and there is exactly one place to audit when the wire contract
 * changes.
 *
 * Hydration uses the "derive state during render" pattern keyed on the
 * server `data` reference — when React Query returns a new payload the form
 * and the server snapshot are reset in lockstep. Saves send a shallow diff
 * so the POST body shows the user's actual change, not the entire option
 * blob, which makes both the network panel and any rate-limited audit log
 * meaningful.
 *
 * @return {SettingsForm}
 */
export function useSettingsForm(): SettingsForm {
	const { data, isLoading, isError } = useSettings();
	const { mutate: save, isPending } = useUpdateSettings();

	const [form, setForm] = useState<PSSettings>(SETTINGS_DEFAULTS);
	const [serverState, setServerState] = useState<PSSettings>(SETTINGS_DEFAULTS);
	const [toast, setToast] = useState<string | null>(null);

	const [syncedData, setSyncedData] = useState<typeof data>();
	if (data && data !== syncedData) {
		const merged = { ...SETTINGS_DEFAULTS, ...data };
		setSyncedData(data);
		setForm(merged);
		setServerState(merged);
	}

	const set = <K extends keyof PSSettings>(key: K, value: PSSettings[K]) =>
		setForm((prev) => ({ ...prev, [key]: value }));

	const dirty = JSON.stringify(form) !== JSON.stringify(serverState);

	function commitSave() {
		if (isPending) {
			return;
		}

		const diff = diffSettings(form, serverState);
		if (Object.keys(diff).length === 0) {
			return;
		}

		save(diff, {
			onSuccess: (next) => {
				const merged = { ...SETTINGS_DEFAULTS, ...next };
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

	return {
		form,
		set,
		dirty,
		save: commitSave,
		revert,
		isPending,
		isLoading,
		isError,
		toast,
		dismissToast: () => setToast(null),
	};
}

/**
 * Persisted disclosure state for the "Advanced" settings section.
 *
 * Stored per-browser in `localStorage` so each admin keeps the section open
 * or collapsed however they prefer without a server round-trip. Returns a
 * tuple identical to `useState` for ergonomic call-sites.
 *
 * @return {readonly [boolean, (open: boolean) => void]}
 */
export function useAdvancedOpen(): readonly [boolean, (open: boolean) => void] {
	const [open, setOpen] = useState<boolean>(() => {
		if (typeof window === 'undefined') {
			return false;
		}
		return window.localStorage.getItem(ADVANCED_OPEN_KEY) === '1';
	});

	useEffect(() => {
		if (typeof window === 'undefined') {
			return;
		}
		window.localStorage.setItem(ADVANCED_OPEN_KEY, open ? '1' : '0');
	}, [open]);

	return [open, setOpen] as const;
}

/**
 * Shallow-diff two settings payloads. Only keys whose values actually
 * changed end up in the result so the save POST body stays minimal.
 *
 * @param {PSSettings} next     Current form state.
 * @param {PSSettings} previous Last known server state.
 *
 * @return {Partial<PSSettings>} Map of changed keys → new values.
 */
function diffSettings(
	next: PSSettings,
	previous: PSSettings
): Partial<PSSettings> {
	const out: Partial<PSSettings> = {};
	(Object.keys(next) as Array<keyof PSSettings>).forEach((key) => {
		if (next[key] !== previous[key]) {
			(out as Record<keyof PSSettings, PSSettings[keyof PSSettings]>)[key] =
				next[key];
		}
	});
	return out;
}
