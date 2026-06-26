/**
 * Lightweight fetch wrapper that handles:
 * - CSRF token (from the <meta name="csrf-token"> tag)
 * - JSON bodies / FormData
 * - Error responses as thrown objects with a `response` property
 */

function getCsrfToken(): string {
    return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';
}

interface FetchError {
    response: {
        status: number;
        data: Record<string, unknown>;
    };
}

async function handleResponse(res: Response) {
    if (res.ok) {
        // 204 No Content
        if (res.status === 204) return undefined;
        return res.json();
    }
    let data: Record<string, unknown> = {};
    try { data = await res.json(); } catch { /* ignore */ }
    const err: FetchError = { response: { status: res.status, data } };
    throw err;
}

function baseHeaders(isFormData = false): Record<string, string> {
    const headers: Record<string, string> = {
        'X-CSRF-TOKEN': getCsrfToken(),
        'X-Requested-With': 'XMLHttpRequest',
        Accept: 'application/json',
    };
    if (!isFormData) headers['Content-Type'] = 'application/json';
    return headers;
}

export const api = {
    async get<T>(url: string, params?: Record<string, string | number | undefined>): Promise<T> {
        let fullUrl = url;
        if (params) {
            const qs = new URLSearchParams(
                Object.entries(params)
                    .filter(([, v]) => v !== undefined)
                    .map(([k, v]) => [k, String(v)])
            ).toString();
            if (qs) fullUrl += `?${qs}`;
        }
        const res = await fetch(fullUrl, { credentials: 'include', headers: baseHeaders() });
        return handleResponse(res) as Promise<T>;
    },

    async post<T>(url: string, body?: Record<string, unknown> | FormData): Promise<T> {
        const isFormData = body instanceof FormData;
        const res = await fetch(url, {
            method: 'POST',
            credentials: 'include',
            headers: baseHeaders(isFormData),
            body: isFormData ? body : JSON.stringify(body),
        });
        return handleResponse(res) as Promise<T>;
    },

    async put<T>(url: string, body?: Record<string, unknown>): Promise<T> {
        const res = await fetch(url, {
            method: 'PUT',
            credentials: 'include',
            headers: baseHeaders(),
            body: JSON.stringify(body),
        });
        return handleResponse(res) as Promise<T>;
    },

    async patch<T>(url: string, body?: Record<string, unknown>): Promise<T> {
        const res = await fetch(url, {
            method: 'PATCH',
            credentials: 'include',
            headers: baseHeaders(),
            body: JSON.stringify(body),
        });
        return handleResponse(res) as Promise<T>;
    },

    async delete<T>(url: string): Promise<T> {
        const res = await fetch(url, {
            method: 'DELETE',
            credentials: 'include',
            headers: baseHeaders(),
        });
        return handleResponse(res) as Promise<T>;
    },
};


/** Helper to extract validation errors from a caught api error */
export function extractErrors(err: unknown): Partial<Record<string, string>> {
    const data = (err as FetchError)?.response?.data ?? {};
    const errors = data.errors as Record<string, string | string[]> | undefined;
    if (!errors) return {};
    // Flatten string[] → string
    return Object.fromEntries(
        Object.entries(errors).map(([k, v]) => [k, Array.isArray(v) ? v[0] : v])
    );
}

/** Helper to get a single error message from a caught api error */
export function getErrorMessage(err: unknown, fallback = 'An error occurred.'): string {
    const data = (err as FetchError)?.response?.data ?? {};
    return (data.message as string) ?? fallback;
}
