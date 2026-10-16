import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { vi } from 'vitest';
import { App } from '../App';

export type MockRoutes = Record<string, { status?: number; body: unknown }>;

/** Nahradí fetch odpověďmi podle cesty (bez /api, včetně query stringu); klíč může mít předponu metody (`POST /parties`). Vrací mock pro kontrolu volání. */
export function mockApi(routes: MockRoutes) {
  const fetchMock = vi.fn(async (url: string, init?: RequestInit) => {
    const path = url.replace('/api', '');
    const hit = routes[`${init?.method ?? 'GET'} ${path}`] ?? routes[path];
    if (!hit) return new Response('{"error":"nf"}', { status: 404 });
    if (hit.status === 204) return new Response(null, { status: 204 });
    return new Response(JSON.stringify(hit.body), { status: hit.status ?? 200 });
  });
  vi.stubGlobal('fetch', fetchMock);
  return fetchMock;
}

export const requestedUrls = (fetchMock: ReturnType<typeof mockApi>) => fetchMock.mock.calls.map((c) => String(c[0]).replace('/api', ''));

export function renderApp(path = '/') {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter initialEntries={[path]}><App /></MemoryRouter>
    </QueryClientProvider>,
  );
}

export const ME = { id: 1, nickname: 'Anna', email: 'a@b.cz', hasNickname: true };
export const PARTY = { id: 3, name: 'Pátek', role: 'admin' };

export const GAME = {
  id: 1, name: 'Carcassonne', scoreType: 'most_points', imageUrl: null, description: null, cheatSheet: null, addons: [],
  playCount: 2, lastPlay: { id: 9, startedAt: '2022-02-06T20:06:00+00:00', sessionNumber: 69, winners: ['Anna'], winnerScore: 90 },
};

export const PLAY = {
  id: 9, sessionId: 7, sessionNumber: 69, game: { id: 1, name: 'Carcassonne', imageUrl: null, scoreType: 'most_points' },
  startedAt: '2022-02-06T20:06:00+00:00', endedAt: '2022-02-06T20:06:00+00:00', place: null, notes: null, addons: [], tags: [],
  results: [{ playerId: 1, name: 'Anna', score: 90, rank: 1, winner: true, starting: true }, { playerId: 2, name: 'Petr', score: 75, rank: 2, winner: false, starting: false }],
};
