/**
 * Shared REST client for the Snopix admin app.
 *
 * Wraps `@wordpress/api-fetch` so the entire app talks to WordPress through
 * the canonical core helper:
 *   - `nonce` middleware (auto-attaches X-WP-Nonce + refreshes after writes)
 *   - `rootURLMiddleware` (resolves `/snopix/v1/foo` against the live REST root)
 *   - 401 / 403 handling integrated with core's user-switch behaviour
 *
 * The wrapper layers Pixel-Scout-specific behaviour on top:
 *   - turn 409 responses into a typed {@link ConflictError} so the UI can
 *     surface the server-supplied "already running" copy
 *   - wrap every other non-2xx as a typed {@link ApiError} with the HTTP
 *     status preserved
 *   - first-call middleware registration (root URL + nonce) so importers
 *     don't need to remember to call `apiFetch.use(...)` themselves
 */

import wpApiFetch from '@wordpress/api-fetch';

declare const snopix_data: { rest_url: string; nonce: string };

let middlewareRegistered = false;

function ensureMiddleware(): void {
	if (middlewareRegistered) {
		return;
	}
	middlewareRegistered = true;

	// snopix_data.rest_url ends with `snopix/v1/` — strip back to the site REST root
	// so callers can pass either bare `snopix/v1/foo` or core paths like
	// `/wp/v2/media/123`.
	const restRoot = snopix_data.rest_url.replace(/snopix\/v1\/?$/, '');
	wpApiFetch.use(wpApiFetch.createRootURLMiddleware(restRoot));
	wpApiFetch.use(wpApiFetch.createNonceMiddleware(snopix_data.nonce));
}

/**
 * Thrown when the server rejects a state-changing request because a
 * conflicting background job is already in flight. The REST handlers emit
 * this with HTTP 409 + a JSON `{ code, message }` payload; the wrapper
 * carries both fields so the UI can show the server-supplied copy.
 */
export class ConflictError extends Error {
	constructor(
		message: string,
		public readonly code: string
	) {
		super(message);
		this.name = 'ConflictError';
	}
}

/**
 * Thrown for any non-2xx response other than 409. Carries the HTTP status so
 * callers can branch (e.g. show a "session expired" prompt on 401/403).
 */
export class ApiError extends Error {
	constructor(
		message: string,
		public readonly status: number
	) {
		super(message);
		this.name = 'ApiError';
	}
}

interface ApiFetchInit {
	/**
	 * REST path. Plugin paths can be passed as `snopix/v1/foo` or `/snopix/v1/foo`;
	 * core paths as `/wp/v2/...`. The `@wordpress/api-fetch` root middleware
	 * resolves either form against the live REST URL.
	 */
	path: string;

	/**
	 * HTTP method. Defaults to GET.
	 */
	method?: string;

	/**
	 * Body that will be JSON-encoded before being sent. Sets
	 * `Content-Type: application/json` automatically. Mutually exclusive with
	 * `formData` and `headers['Content-Type']` overrides.
	 */
	data?: unknown;

	/**
	 * Escape hatch for non-JSON bodies (file uploads, FormData, etc.).
	 * Mutually exclusive with `data`.
	 */
	formData?: FormData;

	/**
	 * Extra headers merged on top of the api-fetch defaults.
	 */
	headers?: Record<string, string>;
}

/**
 * Authenticated REST call backed by `@wordpress/api-fetch`.
 *
 * @param {string|ApiFetchInit} pathOrOpts Either a REST path (defaults to GET)
 *                                         or a full options object.
 *
 * @return {Promise<T>} Parsed JSON body of the response.
 *
 * @throws {ConflictError} When the response status is 409.
 * @throws {ApiError}      When the response status is any other non-2xx.
 */
export async function apiFetch<T = unknown>(
	pathOrOpts: string | ApiFetchInit
): Promise<T> {
	ensureMiddleware();

	const opts: ApiFetchInit =
		typeof pathOrOpts === 'string' ? { path: pathOrOpts } : pathOrOpts;

	const init: Record<string, unknown> = {
		path: opts.path,
		method: opts.method ?? 'GET',
		headers: opts.headers,
	};

	if (opts.formData !== undefined) {
		init.body = opts.formData;
	} else if (opts.data !== undefined) {
		init.data = opts.data;
	}

	try {
		return (await wpApiFetch<T>(init)) as T;
	} catch (err) {
		// `@wordpress/api-fetch` rejects with the parsed JSON body from the
		// server (e.g. `{ code, message, data: { status } }`) rather than a
		// Response object. Translate that shape into our typed errors.
		const body = (err as { code?: string; message?: string; data?: { status?: number } }) ?? {};
		const status = body?.data?.status ?? 0;

		if (status === 409) {
			throw new ConflictError(
				body.message ?? `${opts.path} conflicted with an in-flight job.`,
				body.code ?? 'conflict'
			);
		}

		throw new ApiError(
			body.message ?? `${opts.path} failed`,
			status || 500
		);
	}
}
