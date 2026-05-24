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
