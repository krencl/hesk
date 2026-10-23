import { screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { GAME, mockApi, ME, PARTY, PLAY, renderApp, requestedUrls } from '../test/helpers';

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

describe('who may manage the games and guests of the party', () => {
  const guests = (role: 'admin' | 'writer' | 'member') => ({
    ...routesFor(role),
    '/parties/3': { body: { ...PARTY, role, members: [] } },
    '/parties/3/games?sort=name&limit=21&offset=0': { body: [GAME] },
  });

  it('keeps a plain member away from the game management, its menu item and the add-game page', async () => {
    mockApi(guests('member'));
    const { unmount } = renderApp('/manage/games');
    expect(await screen.findByText('Hry party spravuje jen zapisovač nebo správce.')).toBeInTheDocument();
    expect(screen.queryByRole('heading', { name: 'Správa her' })).not.toBeInTheDocument();
    unmount();

    renderApp('/games/new');
    expect(await screen.findByText('Hry party spravuje jen zapisovač nebo správce.')).toBeInTheDocument();
    expect(screen.queryByLabelText('Název')).not.toBeInTheDocument();
  });

  it.each(['writer', 'admin'] as const)('lets a %s into the game management', async (role) => {
    mockApi(guests(role));
    renderApp('/manage/games');
    expect(await screen.findByRole('heading', { name: 'Správa her' })).toBeInTheDocument();
  });

  it('hides the guests tab and page from a plain member', async () => {
    mockApi(guests('member'));
    const { unmount } = renderApp('/parties/3');
    await screen.findByRole('heading', { name: 'Parta Pátek' });
    expect(screen.queryByRole('link', { name: 'Hosté' })).not.toBeInTheDocument();
    unmount();

    renderApp('/parties/3/guests');
    expect(await screen.findByText('Hosty spravuje jen zapisovač nebo správce.')).toBeInTheDocument();
    expect(screen.queryByPlaceholderText(/Jméno/)).not.toBeInTheDocument();
  });

  it('offers the guests tab to a writer', async () => {
    mockApi(guests('writer'));
    renderApp('/parties/3');
    await screen.findByRole('heading', { name: 'Parta Pátek' });
    expect(screen.getByRole('link', { name: 'Hosté' })).toHaveAttribute('href', '/parties/3/guests');
  });
});
