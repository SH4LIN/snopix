import { useState } from 'react';
import { __ } from '@wordpress/i18n';
import { useSettings, useUpdateSettings, type PSSettings } from '../hooks/use-settings';

/**
 * Settings tab — exposes the same plugin options that used to live under
 * Settings → Connectors → Pixel Scout, so the user does not have to leave the
 * Pixel Scout admin app to manage them.
 *
 * Currently surfaces:
 *   - `search_visibility` — whether the public `[ps_search]` shortcode and
 *     `POST /wp-json/ps/v1/search` endpoint accept anonymous requests or
 *     require the user to be logged in.
 *
 * @return {JSX.Element}
 */
export default function Settings() {
	const { data, isLoading, isError } = useSettings();
	const { mutate: save, isPending, error: saveError } = useUpdateSettings();

	const [visibility, setVisibility] = useState<PSSettings['search_visibility']>(
		data?.search_visibility ?? 'anyone'
	);
	const [lastLoaded, setLastLoaded] = useState<PSSettings['search_visibility'] | undefined>(
		data?.search_visibility
	);
	const [savedAt, setSavedAt] = useState<number | null>(null);

	// Re-sync the form when the server returns a new value (initial load or
	// post-save invalidation). Calling setState during render with an
	// equality guard is the React-blessed derived-state pattern.
	if (data?.search_visibility && data.search_visibility !== lastLoaded) {
		setLastLoaded(data.search_visibility);
		setVisibility(data.search_visibility);
	}

	const dirty = data ? visibility !== data.search_visibility : false;

	const onSave = () => {
		save(
			{ search_visibility: visibility },
			{
				onSuccess: () => setSavedAt(Date.now()),
			}
		);
	};

	if (isLoading) {
		return (
			<div className="ps-card text-sm text-ps-muted">
				{__('Loading settings…', 'pixel-scout')}
			</div>
		);
	}

	if (isError) {
		return (
			<div className="ps-card text-sm text-ps-danger">
				{__('Could not load settings.', 'pixel-scout')}
			</div>
		);
	}

	return (
		<div className="flex flex-col gap-4 max-w-2xl">
			<div className="ps-card flex flex-col gap-4">
				<div>
					<h2 className="text-[15px] font-semibold text-ps-text mb-1">
						{__('Search visibility', 'pixel-scout')}
					</h2>
					<p className="text-[13px] text-ps-muted leading-snug">
						{__(
							'Controls who can use the public reverse-image search endpoint exposed by the [ps_search] shortcode.',
							'pixel-scout'
						)}
					</p>
				</div>

				<div className="flex flex-col gap-2">
					<label className="grid grid-cols-[auto_1fr] items-center gap-x-2 cursor-pointer text-[13px] text-ps-text">
						<input
							type="radio"
							className="!m-0 mt-[3px] flex-shrink-0 accent-[var(--ps-accent,#2271b1)]"
							name="search_visibility"
							value="anyone"
							checked={visibility === 'anyone'}
							onChange={() => setVisibility('anyone')}
						/>
						<span>
							<span className="font-medium">
								{__('Anyone', 'pixel-scout')}
							</span>
							<span className="block text-ps-muted text-[12px]">
								{__(
									'Front-end visitors can drop an image and run a search without logging in.',
									'pixel-scout'
								)}
							</span>
						</span>
					</label>

					<label className="grid grid-cols-[auto_1fr] items-center gap-x-2 cursor-pointer text-[13px] text-ps-text">
						<input
							type="radio"
							className="!m-0 mt-[3px] flex-shrink-0 accent-[var(--ps-accent,#2271b1)]"
							name="search_visibility"
							value="logged_in"
							checked={visibility === 'logged_in'}
							onChange={() => setVisibility('logged_in')}
						/>
						<span>
							<span className="font-medium">
								{__('Logged-in users only', 'pixel-scout')}
							</span>
							<span className="block text-ps-muted text-[12px]">
								{__(
									'Anonymous requests to the search endpoint are rejected with HTTP 401.',
									'pixel-scout'
								)}
							</span>
						</span>
					</label>
				</div>

				<div className="flex items-center gap-3">
					<button
						className="ps-btn"
						onClick={onSave}
						disabled={!dirty || isPending}
					>
						{isPending
							? __('Saving…', 'pixel-scout')
							: __('Save changes', 'pixel-scout')}
					</button>
					{savedAt && !dirty && !isPending && (
						<span className="text-[12px] text-ps-success">
							{__('Saved.', 'pixel-scout')}
						</span>
					)}
					{saveError && (
						<span className="text-[12px] text-ps-danger">
							{__('Could not save. Try again.', 'pixel-scout')}
						</span>
					)}
				</div>
			</div>
		</div>
	);
}
