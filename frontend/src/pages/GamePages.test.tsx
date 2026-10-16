import { fireEvent, screen, waitFor, within } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { GAME, mockApi, ME, PARTY, PLAY, renderApp, requestedUrls } from '../test/helpers';

afterEach(() => { vi.unstubAllGlobals(); sessionStorage.clear(); });

const base = { '/me': { body: ME }, '/parties': { body: [PARTY] } };
const games = (n: number, from = 0) => Array.from({ length: n }, (_, i) => ({ ...GAME, id: from + i + 1, name: `Hra ${from + i + 1}` }));

describe('game list', () => {
  it('redirects the root to the game list', async () => {
    mockApi({ ...base, '/parties/3/games?sort=activity&limit=21&offset=0': { body: [] } });
    renderApp('/');
    expect(await screen.findByText('Zatím tu nejsou žádné hry.')).toBeInTheDocument();
  });

  it('shows games without plays and loads further pages on demand', async () => {
    const fetchMock = mockApi({
      ...base,
      '/parties/3/games?sort=activity&limit=21&offset=0': { body: [...games(20), { ...GAME, id: 99, name: 'Dvacátá první' }] },
      '/parties/3/games?sort=activity&limit=21&offset=20': { body: [{ ...GAME, id: 99, name: 'Dvacátá první', lastPlay: null, playCount: 0 }] },
    });
    renderApp('/games');
    expect(await screen.findByText('Hra 20')).toBeInTheDocument();
    expect(screen.queryByText('Dvacátá první')).not.toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Načíst další' }));
    expect(await screen.findByText('Dvacátá první')).toBeInTheDocument();
    expect(screen.getByText('Zatím nehráno')).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Načíst další' })).not.toBeInTheDocument();
    expect(requestedUrls(fetchMock)).toContain('/parties/3/games?sort=activity&limit=21&offset=20');
  });

  it('searches by name and says so when nothing is found', async () => {
    const fetchMock = mockApi({
      ...base,
      '/parties/3/games?sort=activity&limit=21&offset=0': { body: [GAME] },
      '/parties/3/games?q=xyz&sort=activity&limit=21&offset=0': { body: [] },
    });
    renderApp('/games');
    await screen.findByText('Carcassonne');

    fireEvent.change(screen.getByRole('searchbox', { name: 'Hledat hru' }), { target: { value: 'xyz' } });
    expect(await screen.findByText(/nic nenašlo/)).toBeInTheDocument();
    expect(requestedUrls(fetchMock)).toContain('/parties/3/games?q=xyz&sort=activity&limit=21&offset=0');

    fireEvent.click(screen.getByRole('button', { name: 'Smazat hledání' }));
    expect(await screen.findByText('Carcassonne')).toBeInTheDocument();
  });

  it('filters by sort order and session number, remembering the settings', async () => {
    const fetchMock = mockApi({
      ...base,
      '/parties/3/games?sort=activity&limit=21&offset=0': { body: [GAME] },
      '/parties/3/sessions': { body: [{ id: 7, number: 69, startedAt: '', endedAt: '' }, { id: 6, number: 3, startedAt: '', endedAt: '' }] },
      '/parties/3/games?sort=name&limit=21&offset=0': { body: [GAME] },
      '/parties/3/games?sort=name&sessionNumber=3&limit=21&offset=0': { body: [] },
    });
    renderApp('/games');
    await screen.findByText('Carcassonne');
    expect(screen.queryByLabelText('Řazení')).not.toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Filtr' }));
    fireEvent.change(await screen.findByLabelText('Řazení'), { target: { value: 'name' } });
    await waitFor(() => expect(requestedUrls(fetchMock)).toContain('/parties/3/games?sort=name&limit=21&offset=0'));

    await within(screen.getByLabelText('Sezení')).findByRole('option', { name: '69' });
    fireEvent.change(screen.getByLabelText('Sezení'), { target: { value: '3' } });
    expect(await screen.findByText('V tomhle sezení se nehrála žádná hra.')).toBeInTheDocument();
    expect(JSON.parse(sessionStorage.getItem('hesk.gameList')!)).toMatchObject({ filterVisible: true, sort: 'name', sessionNumber: 3 });

    // vypnutí filtru zruší i filtr seance
    fireEvent.click(screen.getByRole('button', { name: 'Filtr' }));
    expect(await screen.findByText('Carcassonne')).toBeInTheDocument();
  });
});

describe('game detail', () => {
  const older = { ...PLAY, id: 8, sessionNumber: 12, startedAt: '2019-01-01T10:00:00+00:00', addons: [{ id: 1, name: 'Řeka' }], tags: ['rodinná'], notes: 'Skvělá hra',
    results: [{ playerId: 2, name: 'Petr', score: 80, rank: 1, winner: true }, { playerId: 1, name: 'Anna', score: 70, rank: 2, winner: false }] };
  const detailRoutes = (cheatSheet: string | null) => ({
    ...base,
    '/games/1': { body: { ...GAME, playCount: 12, cheatSheet } },
    '/parties/3/plays?limit=11&offset=0&gameId=1': { body: [PLAY, older, ...Array.from({ length: 9 }, (_, i) => ({ ...PLAY, id: 100 + i, sessionNumber: 50 - i }))] },
  });

  it('lists the plays, expands the first one and lets the user open another', async () => {
    mockApi(detailRoutes('<p>Pravidla</p>'));
    renderApp('/games/1');

    const heads = await screen.findAllByRole('button', { expanded: true });
    expect(heads[0]).toHaveTextContent(/6\.2\.2022 \d{2}:\d{2} #69/);
    expect(heads[0]).toHaveTextContent('Anna');
    expect(heads[0]).toHaveTextContent('90');
    expect(screen.getByText('Číslo sezení:')).toBeInTheDocument();
    expect(screen.queryByText('Skvělá hra')).not.toBeInTheDocument();
    expect(screen.getByText(/zbývá 2/)).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: /#12/ }));
    expect(await screen.findByText('Skvělá hra')).toBeInTheDocument();
    expect(screen.getByText('Řeka')).toBeInTheDocument();
    expect(screen.getByText('rodinná')).toBeInTheDocument();

    expect(screen.getByRole('link', { name: 'Nový zápis' })).toHaveAttribute('href', '/games/1/new-play');
    expect(screen.getByRole('link', { name: 'Taháček' })).toHaveAttribute('href', '/games/1/cheatsheet');
    expect(screen.getByRole('link', { name: 'Zpět' })).toHaveAttribute('href', '/games');
  });

  it('hides the cheat-sheet button for games without one and offers editing instead of deleting', async () => {
    mockApi(detailRoutes(null));
    renderApp('/games/1');
    await screen.findByRole('link', { name: 'Nový zápis' });
    expect(screen.queryByRole('link', { name: 'Taháček' })).not.toBeInTheDocument();

    // editace hry je jen ve správě her; zápis se maže až v jeho úpravě
    expect(screen.queryByRole('link', { name: 'Upravit hru' })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Smazat zápis' })).not.toBeInTheDocument();
    expect(screen.queryByRole('link', { name: 'Detail' })).not.toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Upravit' })).toHaveAttribute('href', '/plays/9/edit');
  });

  it('shows a friendly message for an unknown game', async () => {
    mockApi({ ...base, '/games/1': { status: 404, body: { error: 'nf' } } });
    renderApp('/games/1');
    expect(await screen.findByText(/neexistuje nebo k ní nemáš přístup/)).toBeInTheDocument();
  });

  it('renders a sanitized cheat-sheet', async () => {
    mockApi(detailRoutes('<h2>Start hry</h2><script>window.hacked=1</script><img src=x onerror="window.hacked=1">'));
    renderApp('/games/1/cheatsheet');
    expect(await screen.findByRole('heading', { name: 'Start hry' })).toBeInTheDocument();
    expect(document.querySelector('.cheatsheet script')).toBeNull();
    expect(document.querySelector('.cheatsheet img')?.getAttribute('onerror')).toBeNull();
  });
});

describe('new play', () => {
  const players = [{ id: 1, name: 'Anna', hasAccount: true }, { id: 2, name: 'Petr', hasAccount: true }, { id: 3, name: 'Host', hasAccount: false }];
  const routes = {
    ...base,
    '/games/1': { body: { ...GAME, addons: [{ id: 5, name: 'Řeka' }] } },
    '/parties/3/players': { body: players },
    '/parties/3/plays': { body: PLAY },
    '/parties/3/plays?limit=11&offset=0&gameId=1': { body: [PLAY] },
  };

  it('is scoped to the game: no game picker, validates and posts the scores', async () => {
    const fetchMock = mockApi(routes);
    renderApp('/games/1/new-play');
    expect(await screen.findByRole('heading', { name: 'Nový zápis' })).toBeInTheDocument();
    expect(screen.getByText('Carcassonne')).toBeInTheDocument();
    expect(screen.queryByLabelText('Hra')).not.toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Uložit zápis' }));
    expect(await screen.findByRole('alert')).toHaveTextContent('Vyber alespoň jednoho hráče.');

    fireEvent.click(screen.getByLabelText('Anna'));
    fireEvent.click(screen.getByLabelText('Petr'));
    fireEvent.click(screen.getByLabelText('Řeka'));
    fireEvent.change(screen.getByLabelText('Skóre Anna'), { target: { value: '90' } });
    fireEvent.click(screen.getByRole('button', { name: 'Uložit zápis' }));
    expect(await screen.findByRole('alert')).toHaveTextContent('Zadej skóre všem hráčům.');

    fireEvent.change(screen.getByLabelText('Skóre Petr'), { target: { value: '75' } });
    fireEvent.click(screen.getByRole('button', { name: 'Uložit zápis' }));

    await waitFor(() => expect(fetchMock.mock.calls.some(([u, init]) => String(u) === '/api/parties/3/plays' && init?.method === 'POST')).toBe(true));
    const post = fetchMock.mock.calls.find(([u, init]) => String(u) === '/api/parties/3/plays' && init?.method === 'POST')!;
    const body = JSON.parse(String(post[1]?.body));
    expect(body).toMatchObject({ gameId: 1, addonIds: [5] });
    expect(body.players).toEqual([{ playerId: 1, score: 90, starting: false }, { playerId: 2, score: 75, starting: false }]);
    // po uložení se vrací na detail hry
    expect(await screen.findByRole('link', { name: 'Nový zápis' })).toHaveAttribute('href', '/games/1/new-play');
  });

  it('offers the core players first and guests only after "Přidat hosty"', async () => {
    const fetchMock = mockApi({ ...routes, 'POST /parties/3/players': { status: 201, body: { id: 4, name: 'Nový', hasAccount: false } } });
    renderApp('/games/1/new-play');
    await screen.findByRole('heading', { name: 'Nový zápis' });
    expect(screen.getByLabelText('Anna')).toBeInTheDocument();
    expect(screen.queryByLabelText('Host')).not.toBeInTheDocument();
    expect(screen.queryByLabelText('Jméno nového hosta')).not.toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Přidat hosty' }));
    expect(screen.getByLabelText('Host')).toBeInTheDocument();
    fireEvent.change(screen.getByLabelText('Jméno nového hosta'), { target: { value: 'Nový' } });
    fireEvent.click(screen.getByRole('button', { name: 'Přidat' }));
    await waitFor(() => expect(fetchMock.mock.calls.some(([u, init]) => String(u) === '/api/parties/3/players' && init?.method === 'POST')).toBe(true));

    // vybraný host zůstane vidět i po skrytí hostů
    fireEvent.click(screen.getByLabelText('Host'));
    fireEvent.click(screen.getByRole('button', { name: 'Skrýt hosty' }));
    expect(screen.getByLabelText('Host')).toBeChecked();
  });

  it('lets the user pick the winner of a tie and the starting player', async () => {
    const fetchMock = mockApi(routes);
    renderApp('/games/1/new-play');
    await screen.findByRole('heading', { name: 'Nový zápis' });
    fireEvent.click(screen.getByLabelText('Anna'));
    fireEvent.click(screen.getByLabelText('Petr'));
    fireEvent.change(screen.getByLabelText('Skóre Anna'), { target: { value: '50' } });
    fireEvent.change(screen.getByLabelText('Skóre Petr'), { target: { value: '50' } });

    // při shodě skóre jsou automaticky vítězi oba
    expect(screen.getByRole('button', { name: 'Vítěz Anna' })).toHaveAttribute('aria-pressed', 'true');
    expect(screen.getByRole('button', { name: 'Vítěz Petr' })).toHaveAttribute('aria-pressed', 'true');

    // ručně zůstane jen Petr; začínající hráč je nejvýše jeden
    fireEvent.click(screen.getByRole('button', { name: 'Vítěz Anna' }));
    expect(screen.getByRole('button', { name: 'Vítěz Anna' })).toHaveAttribute('aria-pressed', 'false');
    expect(screen.getByRole('button', { name: 'Vítěz Petr' })).toHaveAttribute('aria-pressed', 'true');
    fireEvent.click(screen.getByRole('button', { name: 'Začíná Anna' }));
    fireEvent.click(screen.getByRole('button', { name: 'Začíná Petr' }));
    expect(screen.getByRole('button', { name: 'Začíná Anna' })).toHaveAttribute('aria-pressed', 'false');
    expect(screen.getByRole('button', { name: 'Začíná Petr' })).toHaveAttribute('aria-pressed', 'true');

    fireEvent.click(screen.getByRole('button', { name: 'Uložit zápis' }));
    await waitFor(() => expect(fetchMock.mock.calls.some(([u, init]) => String(u) === '/api/parties/3/plays' && init?.method === 'POST')).toBe(true));
    const post = fetchMock.mock.calls.find(([u, init]) => String(u) === '/api/parties/3/plays' && init?.method === 'POST')!;
    expect(JSON.parse(String(post[1]?.body)).players).toEqual([
      { playerId: 1, score: 50, starting: false, winner: false },
      { playerId: 2, score: 50, starting: true, winner: true },
    ]);
  });

  it('shows a friendly message for an unknown game', async () => {
    mockApi({ ...base, '/parties/3/players': { body: players }, '/games/1': { status: 404, body: { error: 'nf' } } });
    renderApp('/games/1/new-play');
    expect(await screen.findByText(/neexistuje nebo k ní nemáš přístup/)).toBeInTheDocument();
  });
});
