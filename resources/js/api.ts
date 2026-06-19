export class ApiError extends Error {
    constructor(
        public readonly status: number,
        public readonly data: unknown,
    ) {
        super(`HTTP ${status}`);
    }
}

function csrfToken(): string {
    return (
        document
            .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.getAttribute("content") ?? ""
    );
}

async function refreshCsrfToken(): Promise<void> {
    await fetch("/sanctum/csrf-cookie", { credentials: "same-origin" });
    const match = /(?:^|;\s*)XSRF-TOKEN=([^;]+)/.exec(document.cookie);
    if (match) {
        const token = decodeURIComponent(match[1]);
        document
            .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.setAttribute("content", token);
    }
}

function baseHeaders(extra: { [key: string]: string } = {}): {
    [key: string]: string;
} {
    return {
        Accept: "application/json",
        "X-CSRF-TOKEN": csrfToken(),
        ...extra,
    };
}

async function request<T>(
    method: string,
    url: string,
    body?: unknown,
    signal?: AbortSignal,
    retry = true,
): Promise<T> {
    const res = await fetch(url, {
        method,
        credentials: "same-origin",
        headers: baseHeaders({ "Content-Type": "application/json" }),
        body: body !== undefined ? JSON.stringify(body) : undefined,
        signal,
    });

    if (res.status === 419 && retry) {
        await refreshCsrfToken();
        return request<T>(method, url, body, signal, false);
    }

    if (!res.ok) {
        const data: unknown = await res.json().catch(() => null);
        throw new ApiError(res.status, data);
    }

    return res.json() as Promise<T>;
}

function get<T>(
    url: string,
    params?: { [key: string]: string },
    signal?: AbortSignal,
): Promise<T> {
    const qs = params ? "?" + new URLSearchParams(params).toString() : "";
    return request<T>("GET", url + qs, undefined, signal);
}

function post<T>(url: string, body?: unknown): Promise<T> {
    return request<T>("POST", url, body);
}

function put<T>(url: string, body?: unknown): Promise<T> {
    return request<T>("PUT", url, body);
}

function del<T>(url: string): Promise<T> {
    return request<T>("DELETE", url);
}

async function upload<T>(url: string, formData: FormData): Promise<T> {
    const res = await fetch(url, {
        method: "POST",
        credentials: "same-origin",
        headers: {
            Accept: "application/json",
            "X-CSRF-TOKEN": csrfToken(),
            // No Content-Type — browser sets multipart boundary automatically
        },
        body: formData,
    });

    if (!res.ok) {
        const data: unknown = await res.json().catch(() => null);
        throw new ApiError(res.status, data);
    }

    return res.json() as Promise<T>;
}

async function* stream(
    url: string,
    body?: unknown,
    onResponse?: (res: Response) => void,
    retry = true,
): AsyncGenerator<string> {
    const res = await fetch(url, {
        method: "POST",
        credentials: "same-origin",
        headers: baseHeaders({ "Content-Type": "application/json" }),
        body: body !== undefined ? JSON.stringify(body) : undefined,
    });

    if (res.status === 419 && retry) {
        await refreshCsrfToken();
        yield* stream(url, body, onResponse, false);
        return;
    }

    if (!res.ok) {
        throw new Error(`HTTP ${res.status}: ${res.statusText}`);
    }

    onResponse?.(res);

    if (!res.body) {
        throw new Error("Response body is null");
    }

    const reader = res.body.getReader();
    const decoder = new TextDecoder();
    let buffer = "";

    for (
        let chunk = await reader.read();
        !chunk.done;
        chunk = await reader.read()
    ) {
        buffer += decoder.decode(chunk.value, { stream: true });
        const lines = buffer.split("\n");
        buffer = lines.pop() ?? "";

        for (const line of lines) {
            if (!line.startsWith("data: ")) continue;
            const data = line.slice(6);
            if (!data.trim()) continue;
            yield data;
        }
    }
}

export const api = { get, post, put, del, upload, stream };
