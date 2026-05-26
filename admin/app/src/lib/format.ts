/**
 * Shared formatting helpers used across the admin app.
 */

/**
 * Render a byte count using 1024-based units (B / KB / MB), one decimal place.
 *
 * @param {number} bytes Raw byte count, typically from the indexer payload.
 *
 * @return {string} Human-friendly string such as `"512 B"`, `"3.4 KB"`, `"12.1 MB"`.
 */
export function formatBytes(bytes: number): string {
	if (bytes < 1024) {
		return `${bytes} B`;
	}
	if (bytes < 1024 * 1024) {
		return `${(bytes / 1024).toFixed(1)} KB`;
	}
	return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

/**
 * Convert a similarity ratio (0–1) to a whole-number percentage.
 *
 * @param {number} ratio Value in `[0, 1]`.
 *
 * @return {number} Rounded percentage in `[0, 100]`.
 */
export function ratioToPercent(ratio: number): number {
	return Math.round(ratio * 100);
}

/**
 * Convert a whole-number percentage to a similarity ratio rounded to three
 * decimals (matches the precision the PHP sanitizer accepts).
 *
 * @param {number} percent Value in `[0, 100]`.
 *
 * @return {number} Ratio in `[0, 1]`.
 */
export function percentToRatio(percent: number): number {
	return +(percent / 100).toFixed(3);
}
