import { act, fireEvent, screen, waitFor } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { GAME, mockApi, ME, PARTY, PLAY, renderApp, requestedUrls } from '../test/helpers';

afterEach(() => { vi.unstubAllGlobals(); sessionStorage.clear(); });

const base = { '/me': { body: ME }, '/parties': { body: [PARTY] } };
const games = (n: number, from = 0) => Array.from({ length: n }, (_, i) => ({ ...GAME, id: from + i + 1, name: `Hra ${from + i + 1}` }));

describe('game list', () => {
  it('redirects the root to the game list', async () => {
    mockApi({ ...base, '/parties/3/games?sort=activity&limit=21&offset=0': { body: [] } });
    renderApp('/');
    expect(await screen.findByText('Ještě jste nic nehráli, je čas to napravit!')).toBeInTheDocument();
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

  it('loads further pages by scrolling instead of a button', async () => {
    const observers: { callback: IntersectionObserverCallback; disconnected: boolean }[] = [];
    vi.stubGlobal('IntersectionObserver', class {
      entry = { callback: undefined as unknown as IntersectionObserverCallback, disconnected: false };
      constructor(callback: IntersectionObserverCallback) { this.entry.callback = callback; observers.push(this.entry); }
      observe() {}
      disconnect() { this.entry.disconnected = true; }
    });
    const fetchMock = mockApi({
      ...base,
      '/parties/3/games?sort=activity&limit=21&offset=0': { body: [...games(20), { ...GAME, id: 99, name: 'Dvacátá první' }] },
      '/parties/3/games?sort=activity&limit=21&offset=20': { body: [{ ...GAME, id: 99, name: 'Dvacátá první', lastPlay: null, playCount: 0 }] },
    });
    renderApp('/games');
    expect(await screen.findByText('Hra 20')).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Načíst další' })).not.toBeInTheDocument();
    expect(requestedUrls(fetchMock)).not.toContain('/parties/3/games?sort=activity&limit=21&offset=20');

    // hlídač na konci seznamu se objeví ve výhledu
    const active = observers.filter((o) => !o.disconnected).at(-1)!;
    act(() => active.callback([{ isIntersecting: true } as IntersectionObserverEntry], {} as IntersectionObserver));
    expect(await screen.findByText('Dvacátá první')).toBeInTheDocument();
    expect(requestedUrls(fetchMock)).toContain('/parties/3/games?sort=activity&limit=21&offset=20');
    expect(observers.filter((o) => !o.disconnected)).toHaveLength(0); // poslední stránka: hlídač zmizel
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
    expect(screen.queryByRole('button', { name: 'Řazení' })).not.toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Filtr' }));
    fireEvent.click(await screen.findByRole('button', { name: 'Řazení' }));
    fireEvent.click(screen.getByRole('option', { name: 'Název' }));
    await waitFor(() => expect(requestedUrls(fetchMock)).toContain('/parties/3/games?sort=name&limit=21&offset=0'));

    fireEvent.click(screen.getByRole('button', { name: 'Sezení' }));
    await screen.findByRole('option', { name: '69' });
    fireEvent.click(screen.getByRole('option', { name: '3' }));
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
    expect(screen.getByRole('link', { name: 'Cheatsheet' })).toHaveAttribute('href', '/games/1/cheatsheet');
    expect(screen.getByRole('link', { name: 'Zpět' })).toHaveAttribute('href', '/games');
  });

  it('hides the cheat-sheet button for games without one and offers editing instead of deleting', async () => {
    mockApi(detailRoutes(null));
    renderApp('/games/1');
    await screen.findByRole('link', { name: 'Nový zápis' });
    expect(screen.queryByRole('link', { name: 'Cheatsheet' })).not.toBeInTheDocument();

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
  const players = [{ id: 1, name: 'Anna', hasAccount: true, visible: true }, { id: 2, name: 'Petr', hasAccount: true, visible: true }, { id: 3, name: 'Host', hasAccount: false, visible: true }];
  const routes = {
    ...base,
    '/games/1': { body: { ...GAME, addons: [{ id: 5, name: 'Řeka' }] } },
    '/parties/3/players': { body: players },
    '/parties/3/plays': { body: PLAY },
    '/parties/3/plays?limit=11&offset=0&gameId=1': { body: [PLAY] },
    '/parties/3/plays/defaults*': { body: { sessionNumber: 95, playerIds: null } },
    '/parties/3/tags': { body: ['liga', 'víkend'] },
  };

  it('is scoped to the game: no game picker, validates and posts the scores', async () => {
    const fetchMock = mockApi(routes);
    renderApp('/games/1/new-play');
    expect(await screen.findByRole('heading', { name: 'Nový zápis' })).toBeInTheDocument();
    expect(screen.getByText('Carcassonne')).toBeInTheDocument();
    expect(screen.queryByLabelText('Hra')).not.toBeInTheDocument();

    // členové party jsou předvybraní, hosté ne
    expect(screen.getByLabelText('Anna')).toBeChecked();
    expect(screen.getByLabelText('Petr')).toBeChecked();
    fireEvent.click(screen.getByLabelText('Anna'));
    fireEvent.click(screen.getByLabelText('Petr'));
    fireEvent.click(screen.getByRole('button', { name: 'Uložit zápis' }));
    expect(await screen.findByText('Vyber alespoň jednoho hráče.')).toBeInTheDocument();

    fireEvent.click(screen.getByLabelText('Anna'));
    fireEvent.click(screen.getByLabelText('Petr'));
    fireEvent.click(screen.getByLabelText('Řeka'));
    fireEvent.change(screen.getByLabelText('Skóre Anna'), { target: { value: '90' } });
    fireEvent.click(screen.getByRole('button', { name: 'Uložit zápis' }));
    expect(await screen.findByText('Zadej skóre všem hráčům.')).toBeInTheDocument();
    expect(screen.getAllByRole('alert')).toHaveLength(2); // zprávy se mohou zobrazit najednou

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

  it('prefills the session number and the players of the last play of the same session', async () => {
    mockApi({ ...routes, '/parties/3/plays/defaults*': { body: { sessionNumber: 96, playerIds: [3, 2] } } });
    renderApp('/games/1/new-play');
    await screen.findByRole('heading', { name: 'Nový zápis' });
    expect(screen.getByLabelText('Číslo sezení')).toHaveValue(96);
    expect(screen.getByLabelText('Host')).toBeChecked(); // host z minulého zápisu je vidět a vybraný
    expect(screen.getByLabelText('Petr')).toBeChecked();
    expect(screen.getByLabelText('Anna')).not.toBeChecked(); // v minulém zápisu nehrála
  });

  it('prefills the tags of the last play of the same session', async () => {
    mockApi({ ...routes, '/parties/3/plays/defaults*': { body: { sessionNumber: 96, playerIds: [1], tags: ['liga'] } } });
    renderApp('/games/1/new-play');
    await screen.findByRole('heading', { name: 'Nový zápis' });
    expect(screen.getByRole('button', { name: 'Odebrat tag liga' })).toBeInTheDocument();
  });

  it('orders the fields: session number, start and end side by side, players, add-ons', async () => {
    mockApi(routes);
    renderApp('/games/1/new-play');
    await screen.findByRole('heading', { name: 'Nový zápis' });
    const number = screen.getByLabelText('Číslo sezení');
    const start = screen.getByLabelText('Začátek');
    const end = screen.getByLabelText('Konec');
    const players = screen.getByText('Hráči');
    const addons = screen.getByText('Rozšíření');
    const before = (a: Node, b: Node) => expect(a.compareDocumentPosition(b) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
    before(number, start);
    before(start, end);
    before(end, players);
    before(players, addons);
    expect(start.closest('.row')).toBe(end.closest('.row'));
  });

  it('sends the session number only when the user changes it, and creates new tags by typing', async () => {
    const fetchMock = mockApi(routes);
    renderApp('/games/1/new-play');
    await screen.findByRole('heading', { name: 'Nový zápis' });
    expect(screen.getByLabelText('Číslo sezení')).toHaveValue(95);
    fireEvent.change(screen.getByLabelText('Skóre Anna'), { target: { value: '9' } });
    fireEvent.change(screen.getByLabelText('Skóre Petr'), { target: { value: '5' } });

    const tags = screen.getByLabelText('Tagy');
    fireEvent.focus(tags);
    fireEvent.click(await screen.findByRole('option', { name: 'liga' }));
    fireEvent.change(tags, { target: { value: 'novinka' } });
    fireEvent.blur(tags); // klik na „Uložit“ nejdřív opustí input, rozepsaný tag se tím přidá
    fireEvent.click(screen.getByRole('button', { name: 'Uložit zápis' }));

    const isPost = ([u, init]: [string, RequestInit?]) => String(u) === '/api/parties/3/plays' && init?.method === 'POST';
    await waitFor(() => expect(fetchMock.mock.calls.some(isPost)).toBe(true));
    const body = JSON.parse(String(fetchMock.mock.calls.find(isPost)![1]?.body));
    expect(body).toMatchObject({ tags: ['liga', 'novinka'] });
    expect(body).not.toHaveProperty('sessionNumber');
  });

  it('sends a manually entered session number and keeps it when the times change', async () => {
    const fetchMock = mockApi(routes);
    renderApp('/games/1/new-play');
    await screen.findByRole('heading', { name: 'Nový zápis' });
    fireEvent.change(screen.getByLabelText('Číslo sezení'), { target: { value: '120' } });
    fireEvent.change(screen.getByLabelText('Začátek'), { target: { value: '2026-03-01T10:00' } });
    await waitFor(() => expect(fetchMock.mock.calls.filter(([u]) => String(u).includes('/plays/defaults')).length).toBeGreaterThan(1));
    expect(screen.getByLabelText('Číslo sezení')).toHaveValue(120);

    fireEvent.change(screen.getByLabelText('Skóre Anna'), { target: { value: '9' } });
    fireEvent.change(screen.getByLabelText('Skóre Petr'), { target: { value: '5' } });
    fireEvent.click(screen.getByRole('button', { name: 'Uložit zápis' }));
    const isPost = ([u, init]: [string, RequestInit?]) => String(u) === '/api/parties/3/plays' && init?.method === 'POST';
    await waitFor(() => expect(fetchMock.mock.calls.some(isPost)).toBe(true));
    expect(JSON.parse(String(fetchMock.mock.calls.find(isPost)![1]?.body)).sessionNumber).toBe(120);
  });

  it('updates the suggested number when the start time moves to another session', async () => {
    const fetchMock = vi.fn(async (url: string) => {
      const path = String(url).replace('/api', '');
      if (path.startsWith('/parties/3/plays/defaults')) {
        const old = new URL(path, 'http://x').searchParams.get('startedAt')!.startsWith('2026-03');
        return new Response(JSON.stringify({ sessionNumber: old ? 12 : 96, playerIds: null }));
      }
      const hit = (routes as Record<string, { body: unknown }>)[path];
      return hit ? new Response(JSON.stringify(hit.body)) : new Response('{"error":"nf"}', { status: 404 });
    });
    vi.stubGlobal('fetch', fetchMock);
    renderApp('/games/1/new-play');
    await screen.findByRole('heading', { name: 'Nový zápis' });
    expect(screen.getByLabelText('Číslo sezení')).toHaveValue(96);
    fireEvent.change(screen.getByLabelText('Začátek'), { target: { value: '2026-03-01T10:00' } });
    await waitFor(() => expect(screen.getByLabelText('Číslo sezení')).toHaveValue(12));
  });

  it('offers the core players first and guests only after "Přidat hosty"', async () => {
    const fetchMock = mockApi({ ...routes, 'POST /parties/3/players': { status: 201, body: { id: 4, name: 'Nový', hasAccount: false, visible: true } } });
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

  it('does not offer a hidden guest when adding a play', async () => {
    mockApi({ ...routes, '/parties/3/players': { body: [...players, { id: 7, name: 'Skrytý', hasAccount: false, visible: false }] } });
    renderApp('/games/1/new-play');
    await screen.findByRole('heading', { name: 'Nový zápis' });
    fireEvent.click(screen.getByRole('button', { name: 'Přidat hosty' }));
    expect(screen.getByLabelText('Host')).toBeInTheDocument();
    expect(screen.queryByLabelText('Skrytý')).not.toBeInTheDocument();
  });

  it('lets the user pick the winner of a tie and the starting player', async () => {
    const fetchMock = mockApi(routes);
    renderApp('/games/1/new-play');
    await screen.findByRole('heading', { name: 'Nový zápis' });
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

describe('game with characters and an own player', () => {
  const players = [{ id: 1, name: 'Anna', hasAccount: true, visible: true }, { id: 2, name: 'Boss', hasAccount: false, visible: true }, { id: 3, name: 'Host', hasAccount: false, visible: true }];
  const game = { ...GAME, id: 1, players: [{ id: 2, name: 'Boss' }], characters: [{ id: 21, name: 'Gigazaur' }, { id: 22, name: 'Kraken' }] };
  const routes = {
    ...base,
    '/games/1': { body: game },
    '/parties/3/players': { body: players },
    '/parties/3/plays': { body: PLAY },
    '/parties/3/plays?limit=11&offset=0&gameId=1': { body: [PLAY] },
    '/parties/3/plays/defaults*': { body: { sessionNumber: 95, playerIds: null } },
  };

  it('preselects the game own player, shows it first even though it is a guest, and sends the chosen character', async () => {
    const fetchMock = mockApi(routes);
    renderApp('/games/1/new-play');
    await screen.findByRole('heading', { name: 'Nový zápis' });
    expect(screen.getByRole('checkbox', { name: /Boss/ })).toBeChecked();
    expect(screen.getByText('hráč hry')).toBeInTheDocument();
    expect(screen.queryByLabelText('Host')).not.toBeInTheDocument(); // běžný host zůstává schovaný
    const labels = screen.getAllByRole('checkbox').map((c) => c.closest('label')?.textContent ?? '');
    expect(labels[0]).toContain('Boss');

    expect(screen.getByLabelText('Anna')).toBeChecked(); // člen party je předvybraný
    fireEvent.change(screen.getByLabelText('Skóre Anna'), { target: { value: '9' } });
    fireEvent.change(screen.getByLabelText('Skóre Boss'), { target: { value: '5' } });
    fireEvent.change(screen.getByLabelText('Postava Anna'), { target: { value: '22' } });
    expect(screen.getByLabelText('Postava Boss')).toHaveValue(''); // postava je volitelná
    fireEvent.click(screen.getByRole('button', { name: 'Uložit zápis' }));

    await waitFor(() => expect(fetchMock.mock.calls.some(([u, init]) => String(u) === '/api/parties/3/plays' && init?.method === 'POST')).toBe(true));
    const post = fetchMock.mock.calls.find(([u, init]) => String(u) === '/api/parties/3/plays' && init?.method === 'POST')!;
    expect(JSON.parse(String(post[1]?.body)).players).toEqual([
      { playerId: 2, score: 5, starting: false },
      { playerId: 1, score: 9, starting: false, characterId: 22 },
    ]);
  });

  it('shows the played character next to the player in the game detail', async () => {
    const play = { ...PLAY, results: [{ ...PLAY.results[0], character: { id: 21, name: 'Gigazaur' } }, PLAY.results[1]] };
    mockApi({ ...routes, '/parties/3/plays?limit=11&offset=0&gameId=1': { body: [play] } });
    renderApp('/games/1');
    expect(await screen.findByText('Gigazaur')).toHaveClass('result-character');
  });
});
