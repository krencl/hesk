import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { vi } from 'vitest';
import { App } from '../App';

export type MockRoutes = Record<string, { status?: number; body: unknown }>;

/** Cesta s `*` na konci (`/parties/3/plays/defaults*`) pokryje i query string, který se mění (např. čas „teď“). */
function wildcard(routes: MockRoutes, withMethod: string, path: string) {
  const key = Object.keys(routes).find((k) => k.endsWith('*') && (withMethod.startsWith(k.slice(0, -1)) || path.startsWith(k.slice(0, -1))));
  return key ? routes[key] : undefined;
}

/** Nahradí fetch odpověďmi podle cesty (bez /api, včetně query stringu; na konci může být `*`); klíč může mít předponu metody (`POST /parties`). Vrací mock pro kontrolu volání. */
export function mockApi(routes: MockRoutes) {
  const fetchMock = vi.fn(async (url: string, init?: RequestInit) => {
    const path = url.replace('/api', '');
    const hit = routes[`${init?.method ?? 'GET'} ${path}`] ?? routes[path] ?? wildcard(routes, `${init?.method ?? 'GET'} ${path}`, path);
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
export const PARTY = { id: 3, name: 'Pátek', shortName: 'Pá', role: 'admin' };

export const GAME = {
  id: 1, name: 'Carcassonne', shortName: null, scoreType: 'most_points', imageUrl: null, bggId: null, description: null, cheatSheet: null, addons: [], characters: [], players: [],
  playCount: 2, lastPlay: { id: 9, startedAt: '2022-02-06T20:06:00+00:00', sessionNumber: 69, winners: ['Anna'], winnerScore: 90 },
};

export const PLAY = {
  id: 9, sessionId: 7, sessionNumber: 69, game: { id: 1, name: 'Carcassonne', imageUrl: null, scoreType: 'most_points' },
  startedAt: '2022-02-06T20:06:00+00:00', endedAt: '2022-02-06T20:06:00+00:00', notes: null, addons: [], tags: [],
  results: [{ playerId: 1, name: 'Anna', score: 90, rank: 1, winner: true, starting: true, character: null }, { playerId: 2, name: 'Petr', score: 75, rank: 2, winner: false, starting: false, character: null }],
};
