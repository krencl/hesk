import { describe, expect, it, vi } from 'vitest';
import { ApiError, get, post } from './client';

const respond = (status: number, body?: unknown) =>
  vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response(body === undefined ? null : JSON.stringify(body), { status })));

describe('api client', () => {
  it('sends credentials and parses JSON', async () => {
    respond(200, { ok: true });
    await expect(get('/health')).resolves.toEqual({ ok: true });
    expect(fetch).toHaveBeenCalledWith('/api/health', expect.objectContaining({ method: 'GET', credentials: 'include' }));
  });

  it('serialises JSON bodies', async () => {
    respond(201, { id: 1 });
    await post('/parties', { name: 'A' });
    const init = vi.mocked(fetch).mock.calls[0][1]!;
    expect(init.body).toBe('{"name":"A"}');
    expect((init.headers as Record<string, string>)['Content-Type']).toBe('application/json');
  });

  it('returns undefined for 204', async () => {
    respond(204);
    await expect(post('/auth/logout')).resolves.toBeUndefined();
  });

  it('throws ApiError with status and violations', async () => {
    respond(422, { error: 'Validation failed', violations: [{ field: 'name', message: 'Povinné' }] });
    const err = await get('/x').catch((e: unknown) => e);
    expect(err).toBeInstanceOf(ApiError);
    expect(err).toMatchObject({ status: 422, message: 'Validation failed', violations: [{ field: 'name', message: 'Povinné' }] });
  });

  it('survives non-JSON error bodies', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response('<html>Bad gateway</html>', { status: 502, statusText: 'Bad Gateway' })));
    await expect(get('/x')).rejects.toMatchObject({ status: 502, message: 'Bad Gateway' });
  });

  it('gives errors without any text (e.g. HTTP/2 500 with an HTML body) a readable message', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response('<html>Oops</html>', { status: 500, statusText: '' })));
    await expect(get('/x')).rejects.toMatchObject({ status: 500, message: 'Na serveru nastala chyba (500). Zkus to prosím později.' });
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response('{"error":""}', { status: 500 })));
    await expect(get('/x')).rejects.toMatchObject({ message: 'Na serveru nastala chyba (500). Zkus to prosím později.' });
  });
});
