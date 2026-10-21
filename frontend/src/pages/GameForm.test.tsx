import { fireEvent, screen, waitFor } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { GAME, mockApi, ME, PARTY, renderApp } from '../test/helpers';

afterEach(() => { vi.unstubAllGlobals(); sessionStorage.clear(); });

const PLAYERS = [
  { id: 1, name: 'Anna', hasAccount: true, visible: true },
  { id: 2, name: 'Boss', hasAccount: false, visible: true },
  { id: 3, name: 'Host', hasAccount: false, visible: true },
];
const FULL_GAME = {
  ...GAME, id: 5, name: 'King of Tokyo', shortName: 'Tokyo', scoreType: 'winner_only', cheatSheet: '# Pravidla',
  addons: [{ id: 11, name: 'Power Up' }, { id: 12, name: 'Halloween' }],
  characters: [{ id: 21, name: 'Gigazaur' }, { id: 22, name: 'Kraken' }],
  player: { id: 2, name: 'Boss' },
};
const routes = {
  '/me': { body: ME },
  '/parties': { body: [PARTY] },
  '/games/5': { body: FULL_GAME },
  '/parties/3/players': { body: PLAYERS },
  '/parties/3/games?sort=name&limit=21&offset=0': { body: [FULL_GAME] },
};
const sent = (fetchMock: ReturnType<typeof mockApi>, method: string, url: string) => {
  const call = fetchMock.mock.calls.find(([u, init]) => String(u) === `/api${url}` && init?.method === method);
  return call ? JSON.parse(String(call[1]?.body)) : undefined;
};

describe('game form (/games/:id/edit)', () => {
  it('prefills short name, extensions, characters and the own player', async () => {
    mockApi(routes);
    renderApp('/games/5/edit');
    expect(await screen.findByRole('heading', { name: 'Upravit hru' })).toBeInTheDocument();
    await waitFor(() => expect(screen.getByLabelText('Název')).toHaveValue('King of Tokyo'));
    expect(screen.getByLabelText('Zkrácený název')).toHaveValue('Tokyo');
    expect(screen.getByLabelText('Rozšíření 1')).toHaveValue('Power Up');
    expect(screen.getByLabelText('Rozšíření 2')).toHaveValue('Halloween');
    expect(screen.getByLabelText('Postava 1')).toHaveValue('Gigazaur');
    expect(screen.getByLabelText('Postava 2')).toHaveValue('Kraken');
    // hráč hry je textové pole se jménem, ne výběr z existujících hráčů
    expect(screen.getByLabelText('Hráč hry')).toHaveValue('Boss');
    expect(screen.queryByRole('option', { name: 'Host' })).not.toBeInTheDocument();
    // úprava hry je jediné místo, odkud se odchází zpět do správy her
    expect(screen.getByRole('link', { name: 'Zpět' })).toHaveAttribute('href', '/manage/games');
  });

  it('saves renamed items with their ids, new ones without, and the removed ones are left out', async () => {
    const fetchMock = mockApi({ ...routes, 'PUT /games/5': { body: FULL_GAME } });
    renderApp('/games/5/edit');
    await waitFor(() => expect(screen.getByLabelText('Název')).toHaveValue('King of Tokyo'));

    fireEvent.change(screen.getByLabelText('Zkrácený název'), { target: { value: ' KoT ' } });
    fireEvent.change(screen.getByLabelText('Rozšíření 1'), { target: { value: 'Power Up 2' } });
    fireEvent.click(screen.getByRole('button', { name: 'Odebrat rozšíření 2' }));
    fireEvent.click(screen.getByRole('button', { name: 'Přidat rozšíření' }));
    fireEvent.change(screen.getByLabelText('Rozšíření 2'), { target: { value: 'Nové rozšíření' } });
    fireEvent.change(screen.getByLabelText('Postava 2'), { target: { value: 'Kraken Mk2' } });
    fireEvent.click(screen.getByRole('button', { name: 'Přidat postavu' }));
    fireEvent.click(screen.getByRole('button', { name: 'Uložit' }));

    await waitFor(() => expect(sent(fetchMock, 'PUT', '/games/5')).toBeDefined());
    expect(sent(fetchMock, 'PUT', '/games/5')).toMatchObject({
      name: 'King of Tokyo', shortName: 'KoT', scoreType: 'winner_only', playerId: 2,
      addons: [{ id: 11, name: 'Power Up 2' }, { name: 'Nové rozšíření' }],
      characters: [{ id: 21, name: 'Gigazaur' }, { id: 22, name: 'Kraken Mk2' }],
    });
    expect(await screen.findByRole('heading', { name: 'Správa her' })).toBeInTheDocument();
  });

  it('keeps the own player when its name is unchanged', async () => {
    const fetchMock = mockApi({ ...routes, 'PUT /games/5': { body: FULL_GAME } });
    renderApp('/games/5/edit');
    await waitFor(() => expect(screen.getByLabelText('Hráč hry')).toHaveValue('Boss'));
    fireEvent.click(screen.getByRole('button', { name: 'Uložit' }));
    await waitFor(() => expect(sent(fetchMock, 'PUT', '/games/5')?.playerId).toBe(2));
    expect(sent(fetchMock, 'PATCH', '/players/2')).toBeUndefined();
  });

  it('renames the own player in place when its name is changed', async () => {
    const fetchMock = mockApi({ ...routes, 'PUT /games/5': { body: FULL_GAME }, 'PATCH /players/2': { body: { id: 2, name: 'Šéf', hasAccount: false, visible: true } } });
    renderApp('/games/5/edit');
    await waitFor(() => expect(screen.getByLabelText('Hráč hry')).toHaveValue('Boss'));
    fireEvent.change(screen.getByLabelText('Hráč hry'), { target: { value: ' Šéf ' } });
    fireEvent.click(screen.getByRole('button', { name: 'Uložit' }));
    await waitFor(() => expect(sent(fetchMock, 'PUT', '/games/5')?.playerId).toBe(2));
    expect(sent(fetchMock, 'PATCH', '/players/2')).toEqual({ name: 'Šéf' });
    expect(sent(fetchMock, 'POST', '/parties/3/players')).toBeUndefined();
  });

  it('removes the own player when the name is cleared', async () => {
    const fetchMock = mockApi({ ...routes, 'PUT /games/5': { body: FULL_GAME } });
    renderApp('/games/5/edit');
    await waitFor(() => expect(screen.getByLabelText('Hráč hry')).toHaveValue('Boss'));
    fireEvent.change(screen.getByLabelText('Hráč hry'), { target: { value: '' } });
    fireEvent.click(screen.getByRole('button', { name: 'Uložit' }));
    await waitFor(() => expect(sent(fetchMock, 'PUT', '/games/5')?.playerId).toBeNull());
  });

  it('creates a new player for a game that has none when a name is typed', async () => {
    const fetchMock = mockApi({
      ...routes,
      '/games/5': { body: { ...FULL_GAME, player: null } },
      'PUT /games/5': { body: FULL_GAME },
      'POST /parties/3/players': { status: 201, body: { id: 9, name: 'T.I.M.E', hasAccount: false, visible: true } },
    });
    renderApp('/games/5/edit');
    await waitFor(() => expect(screen.getByLabelText('Název')).toHaveValue('King of Tokyo'));
    expect(screen.getByLabelText('Hráč hry')).toHaveValue('');
    fireEvent.change(screen.getByLabelText('Hráč hry'), { target: { value: 'T.I.M.E' } });
    fireEvent.click(screen.getByRole('button', { name: 'Uložit' }));

    await waitFor(() => expect(sent(fetchMock, 'POST', '/parties/3/players')).toEqual({ name: 'T.I.M.E' }));
    await waitFor(() => expect(sent(fetchMock, 'PUT', '/games/5')?.playerId).toBe(9));
  });

  it('new game starts with empty lists and no own player', async () => {
    const fetchMock = mockApi({ ...routes, 'POST /parties/3/games': { status: 201, body: FULL_GAME } });
    renderApp('/games/new');
    expect(await screen.findByRole('heading', { name: 'Nová hra' })).toBeInTheDocument();
    expect(screen.getByText('Hra nemá rozšíření.')).toBeInTheDocument();
    expect(screen.getByText('Hra nemá postavy.')).toBeInTheDocument();
    expect(screen.getByLabelText('Hráč hry')).toHaveValue('');
    fireEvent.change(screen.getByLabelText('Název'), { target: { value: 'Nová' } });
    fireEvent.click(screen.getByRole('button', { name: 'Uložit' }));
    await waitFor(() => expect(sent(fetchMock, 'POST', '/parties/3/games')).toMatchObject({ name: 'Nová', shortName: null, addons: [], characters: [], playerId: null }));
  });
});
