export class ApiError extends Error {
  constructor(
    public readonly status: number,
    message: string,
    public readonly violations: { field: string; message: string }[] = [],
  ) {
    super(message);
    this.name = 'ApiError';
  }
}

/** Tenká obálka nad fetch: session cookie, JSON v obou směrech, chyby jako ApiError. */
export async function request<T>(method: string, path: string, body?: unknown): Promise<T> {
  const res = await fetch(`/api${path}`, {
    method,
    credentials: 'include',
    headers: body === undefined ? { Accept: 'application/json' } : { Accept: 'application/json', 'Content-Type': 'application/json' },
    body: body === undefined ? undefined : JSON.stringify(body),
  });
  if (res.status === 204) {
    return undefined as T;
  }
  const text = await res.text();
  let data: unknown = null;
  try {
    data = text ? JSON.parse(text) : null;
  } catch {
    // neJSON odpověď (např. chyba proxy) – použijeme obecnou zprávu níže
  }
  if (!res.ok) {
    const err = (data ?? {}) as { error?: string; violations?: { field: string; message: string }[] };
    throw new ApiError(res.status, err.error ?? res.statusText, err.violations);
  }
  return data as T;
}

export const get = <T>(path: string) => request<T>('GET', path);
export const post = <T>(path: string, body?: unknown) => request<T>('POST', path, body ?? {});
export const put = <T>(path: string, body: unknown) => request<T>('PUT', path, body);
export const patch = <T>(path: string, body: unknown) => request<T>('PATCH', path, body);
export const del = (path: string) => request<void>('DELETE', path);
