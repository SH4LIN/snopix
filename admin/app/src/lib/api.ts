/**
 * Shared REST client for the Pixel Scout admin app.
 *
 * Every hook and component talking to `/wp-json/ps/v1/*` should route through
 * {@link apiFetch} instead of calling `fetch` directly. Centralising the call
 * gives one place to:
 *   - prepend `ps_data.rest_url`
 *   - attach the `X-WP-Nonce` header
 *   - serialise / set `Content-Type` for JSON bodies
 *   - turn 409 responses into a {@link ConflictError} so callers can surface
 *     the server-supplied "already running" message
 *   - throw a typed `ApiError` for any other non-2xx status
 *
 * The function deliberately returns the parsed JSON body as `T`. Callers that
 * want the raw `Response` should drop down to `fetch` themselves — that hatch
 * has not been needed yet.
 */

declare const ps_data: { rest_url: string; nonce: string };

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

interface ApiFetchInit extends Omit<RequestInit, 'body'> {
	/**
	 * Request body. When set, the value is JSON-encoded and a
	 * `Content-Type: application/json` header is added automatically. Pass a
	 * `FormData`/`Blob`/string yourself via `rawBody` if you need to bypass
	 * JSON encoding (e.g. file uploads).
	 */
	json?: unknown;

	/**
	 * Escape hatch for non-JSON bodies (file uploads, FormData, etc.).
	 * Mutually exclusive with `json`.
	 */
	rawBody?: BodyInit | null;
}

/**
 * Authenticated REST call against the Pixel Scout namespace.
 *
 * @param {string}       path REST sub-path appended to `ps_data.rest_url` (no leading slash).
 * @param {ApiFetchInit} init Optional method/headers/body overrides.
 *
 * @return {Promise<T>} Parsed JSON body of the response.
 *
 * @throws {ConflictError} When the response status is 409.
 * @throws {ApiError}      When the response status is any other non-2xx.
 */
export async function apiFetch<T = unknown>(
	path: string,
	init: ApiFetchInit = {}
): Promise<T> {
	const headers: Record<string, string> = {
		'X-WP-Nonce': ps_data.nonce,
		...((init.headers as Record<string, string>) ?? {}),
	};

	let body: BodyInit | null | undefined = init.rawBody;
	if (init.json !== undefined) {
		body = JSON.stringify(init.json);
		headers['Content-Type'] = headers['Content-Type'] ?? 'application/json';
	}

	const res = await fetch(`${ps_data.rest_url}${path}`, {
		...init,
		headers,
		body,
	});

	if (res.status === 409) {
		const payload = await res.json().catch(() => ({}));
		throw new ConflictError(
			(payload as { message?: string })?.message ??
				`${path} conflicted with an in-flight job.`,
			(payload as { code?: string })?.code ?? 'conflict'
		);
	}

	if (!res.ok) {
		const payload = await res.json().catch(() => ({}));
		throw new ApiError(
			(payload as { message?: string })?.message ?? `${path} failed`,
			res.status
		);
	}

	// 204 No Content is rare for Pixel Scout but worth handling — return an
	// empty object cast to T so callers don't need to special-case it.
	if (res.status === 204) {
		return {} as T;
	}

	return (await res.json()) as T;
}
