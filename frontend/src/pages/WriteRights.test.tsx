import { screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { GAME, mockApi, ME, PARTY, PLAY, renderApp, requestedUrls } from '../test/helpers';

afterEach(() => { vi.unstubAllGlobals(); sessionStorage.clear(); localStorage.clear(); });

const routesFor = (role: 'admin' | 'writer' | 'member') => ({
  '/me': { body: ME },
  '/parties': { body: [{ ...PARTY, role }] },
  '/parties/3/games/1': { body: GAME },
  '/plays/9': { body: PLAY },
  '/parties/3/players': { body: [{ id: 1, name: 'Anna', hasAccount: true, visible: true }, { id: 2, name: 'Petr', hasAccount: true, visible: true }] },
  '/parties/3/plays/defaults*': { body: { sessionNumber: 70, playerIds: null, tags: [] } },
  '/parties/3/tags': { body: [] },
  '/parties/3/plays?limit=11&offset=0&gameId=1': { body: [PLAY] },
});

describe('who may write plays', () => {
  it('shows a plain member the plays without the add and edit buttons', async () => {
    mockApi(routesFor('member'));
    renderApp('/games/1');
    expect(await screen.findByText('Petr')).toBeInTheDocument();
    expect(screen.queryByRole('link', { name: 'Nový zápis' })).not.toBeInTheDocument();
    expect(screen.queryByRole('link', { name: 'Upravit' })).not.toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Zpět' })).toBeInTheDocument();
  });

  it.each(['writer', 'admin'] as const)('offers a %s the add and edit buttons', async (role) => {
    mockApi(routesFor(role));
    renderApp('/games/1');
    expect(await screen.findByRole('link', { name: 'Nový zápis' })).toHaveAttribute('href', '/games/1/new-play');
    expect(screen.getByRole('link', { name: 'Upravit' })).toHaveAttribute('href', '/plays/9/edit');
  });

  it('blocks the new play page for a member without asking the server for the defaults', async () => {
    const fetchMock = mockApi(routesFor('member'));
    renderApp('/games/1/new-play');
    expect(await screen.findByText('Zapisovat hry může jen zapisovač nebo správce.')).toBeInTheDocument();
    expect(screen.queryByRole('heading', { name: 'Nový zápis' })).not.toBeInTheDocument();
    expect(requestedUrls(fetchMock).some((u) => u.includes('/plays/defaults'))).toBe(false);
    expect(screen.getByRole('link', { name: 'Zpět' })).toHaveAttribute('href', '/games/1');
  });

  it('blocks the edit page for a member', async () => {
    mockApi(routesFor('member'));
    renderApp('/plays/9/edit');
    expect(await screen.findByText('Upravovat zápisy může jen zapisovač nebo správce.')).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Smazat zápis' })).not.toBeInTheDocument();
  });

  it('lets a writer open the new play form', async () => {
    mockApi(routesFor('writer'));
    renderApp('/games/1/new-play');
    expect(await screen.findByRole('heading', { name: 'Nový zápis' })).toBeInTheDocument();
  });
});
