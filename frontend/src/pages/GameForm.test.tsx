import { fireEvent, screen, waitFor } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
vi.mock('react-easy-crop', () => ({
  // zjednodušený editor: hned ohlásí výřez, jako když ho uživatel nastaví
  default: ({ onCropComplete }: { onCropComplete: (a: unknown, p: unknown) => void }) => (
    <button type="button" onClick={() => onCropComplete({}, { x: 10, y: 20, width: 300, height: 300 })}>Nastavit výřez</button>
  ),
}));
vi.mock('../lib/cropImage', () => ({ cropToBlob: vi.fn(async () => new Blob(['x'], { type: 'image/jpeg' })) }));

import { cropToBlob } from '../lib/cropImage';
import { GAME, GAMES_ADMIN, mockApi, ME, PARTY, renderApp, requestedUrls } from '../test/helpers';

afterEach(() => { vi.unstubAllGlobals(); vi.clearAllMocks(); sessionStorage.clear(); });

const PLAYERS = [
  { id: 1, name: 'Anna', hasAccount: true, visible: true },
  { id: 2, name: 'Boss', hasAccount: false, visible: true },
  { id: 3, name: 'Host', hasAccount: false, visible: true },
];
const FULL_GAME = {
  ...GAME, id: 5, name: 'King of Tokyo', shortName: 'Tokyo', scoreType: 'winner_only', cheatSheet: '# Pravidla',
  addons: [{ id: 11, name: 'Power Up' }, { id: 12, name: 'Halloween' }],
  characters: [{ id: 21, name: 'Gigazaur' }, { id: 22, name: 'Kraken' }],
  players: [{ id: 2, name: 'Boss' }, { id: 3, name: 'Host' }],
};
const routes = {
  '/me': { body: GAMES_ADMIN },
  '/parties': { body: [PARTY] },
  '/parties/3/games/5': { body: FULL_GAME },
  'PUT /parties/3/games/5/players': { body: FULL_GAME },
  '/parties/3/players': { body: PLAYERS },
  '/parties/3/games?sort=name&limit=21&offset=0': { body: [FULL_GAME] },
};
const stubUrls = () => {
  vi.stubGlobal('URL', Object.assign(URL, { createObjectURL: vi.fn(() => 'blob:cover'), revokeObjectURL: vi.fn() }));
};
const sent = (fetchMock: ReturnType<typeof mockApi>, method: string, url: string) => {
  const call = fetchMock.mock.calls.find(([u, init]) => String(u) === `/api${url}` && init?.method === method);
  return call ? JSON.parse(String(call[1]?.body)) : undefined;
};

describe('game form (/games/:id/edit)', () => {
  it('prefills short name, extensions, characters and the own players', async () => {
    mockApi(routes);
    renderApp('/games/5/edit');
    expect(await screen.findByRole('heading', { name: 'Upravit hru' })).toBeInTheDocument();
    await waitFor(() => expect(screen.getByLabelText('Název')).toHaveValue('King of Tokyo'));
    expect(screen.getByLabelText('Zkrácený název')).toHaveValue('Tokyo');
    expect(screen.getByLabelText('Rozšíření 1')).toHaveValue('Power Up');
    expect(screen.getByLabelText('Rozšíření 2')).toHaveValue('Halloween');
    expect(screen.getByLabelText('Postava 1')).toHaveValue('Gigazaur');
    expect(screen.getByLabelText('Postava 2')).toHaveValue('Kraken');
    // hráči hry se edituje jménem jako postavy, ne výběrem z existujících hráčů
    expect(screen.getByLabelText('Hráč hry 1')).toHaveValue('Boss');
    expect(screen.getByLabelText('Hráč hry 2')).toHaveValue('Host');
    expect(screen.queryByRole('combobox', { name: /Hráč/ })).not.toBeInTheDocument();
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
      name: 'King of Tokyo', shortName: 'KoT', scoreType: 'winner_only', players: [{ id: 2, name: 'Boss' }, { id: 3, name: 'Host' }],
      addons: [{ id: 11, name: 'Power Up 2' }, { name: 'Nové rozšíření' }],
      characters: [{ id: 21, name: 'Gigazaur' }, { id: 22, name: 'Kraken Mk2' }],
    });
    expect(await screen.findByRole('heading', { name: 'Správa her' })).toBeInTheDocument();
  });

  it('keeps the own players by id when their names are unchanged', async () => {
    const fetchMock = mockApi({ ...routes, 'PUT /games/5': { body: FULL_GAME } });
    renderApp('/games/5/edit');
    await waitFor(() => expect(screen.getByLabelText('Hráč hry 1')).toHaveValue('Boss'));
    fireEvent.click(screen.getByRole('button', { name: 'Uložit' }));
    await waitFor(() => expect(sent(fetchMock, 'PUT', '/parties/3/games/5/players')?.players).toEqual([{ id: 2, name: 'Boss' }, { id: 3, name: 'Host' }]));
  });

  it('renames an own player by id, drops a removed one and adds a new one by name', async () => {
    const fetchMock = mockApi({ ...routes, 'PUT /games/5': { body: FULL_GAME } });
    renderApp('/games/5/edit');
    await waitFor(() => expect(screen.getByLabelText('Hráč hry 1')).toHaveValue('Boss'));
    fireEvent.change(screen.getByLabelText('Hráč hry 1'), { target: { value: ' Šéf ' } });
    fireEvent.click(screen.getByRole('button', { name: 'Odebrat hráče hry 2' }));
    fireEvent.click(screen.getByRole('button', { name: 'Přidat hráče hry' }));
    fireEvent.change(screen.getByLabelText('Hráč hry 2'), { target: { value: 'T.I.M.E' } });
    fireEvent.click(screen.getByRole('button', { name: 'Uložit' }));
    await waitFor(() => expect(sent(fetchMock, 'PUT', '/parties/3/games/5/players')?.players).toEqual([{ id: 2, name: 'Šéf' }, { name: 'T.I.M.E' }]));
  });

  it('can remove all own players', async () => {
    const fetchMock = mockApi({ ...routes, 'PUT /games/5': { body: FULL_GAME } });
    renderApp('/games/5/edit');
    await waitFor(() => expect(screen.getByLabelText('Hráč hry 1')).toHaveValue('Boss'));
    fireEvent.click(screen.getByRole('button', { name: 'Odebrat hráče hry 1' }));
    fireEvent.click(screen.getByRole('button', { name: 'Odebrat hráče hry 1' }));
    expect(screen.getByText('Hra nemá vlastní hráče.')).toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: 'Uložit' }));
    await waitFor(() => expect(sent(fetchMock, 'PUT', '/parties/3/games/5/players')?.players).toEqual([]));
  });

  describe('shared game and rights', () => {
    const asMember = { ...routes, '/me': { body: ME } };

    it('locks the shared fields for a non-admin, who can still change the own players of the party', async () => {
      const fetchMock = mockApi({ ...asMember, 'PUT /games/5': { body: FULL_GAME } });
      renderApp('/games/5/edit');
      await waitFor(() => expect(screen.getByLabelText('Název')).toHaveValue('King of Tokyo'));

      expect(screen.getByLabelText('Název')).toBeDisabled();
      expect(screen.getByLabelText('Rozšíření 1')).toBeDisabled();
      expect(screen.getByLabelText('Hráč hry 1')).toBeEnabled();
      expect(screen.getByText(/upravuje je jen správce her/)).toBeInTheDocument();
      expect(screen.queryByRole('button', { name: 'Smazat hru z databáze' })).not.toBeInTheDocument();

      fireEvent.change(screen.getByLabelText('Hráč hry 1'), { target: { value: 'Šéf' } });
      fireEvent.click(screen.getByRole('button', { name: 'Uložit' }));
      await waitFor(() => expect(sent(fetchMock, 'PUT', '/parties/3/games/5/players')?.players).toEqual([{ id: 2, name: 'Šéf' }, { id: 3, name: 'Host' }]));
      expect(sent(fetchMock, 'PUT', '/games/5')).toBeUndefined();
    });

    it('lets the admin edit the shared data and delete the game from the catalog', async () => {
      const fetchMock = mockApi({ ...routes, 'PUT /games/5': { body: FULL_GAME } });
      renderApp('/games/5/edit');
      await waitFor(() => expect(screen.getByLabelText('Název')).toBeEnabled());
      expect(screen.getByRole('button', { name: 'Smazat hru z databáze' })).toBeInTheDocument();

      fireEvent.click(screen.getByRole('button', { name: 'Uložit' }));
      await waitFor(() => expect(sent(fetchMock, 'PUT', '/games/5')).toBeDefined());
      await waitFor(() => expect(sent(fetchMock, 'PUT', '/parties/3/games/5/players')).toBeDefined());
    });

    it('removes the game from the party only, after confirmation', async () => {
      vi.spyOn(window, 'confirm').mockReturnValue(true);
      const fetchMock = mockApi({ ...asMember, 'DELETE /parties/3/games/5': { status: 204, body: null } });
      renderApp('/games/5/edit');
      await waitFor(() => expect(screen.getByLabelText('Název')).toHaveValue('King of Tokyo'));

      fireEvent.click(screen.getByRole('button', { name: 'Odebrat z party' }));

      await waitFor(() => expect(fetchMock.mock.calls.some(([u, init]) => init?.method === 'DELETE' && String(u) === '/api/parties/3/games/5')).toBe(true));
      expect(fetchMock.mock.calls.some(([u, init]) => init?.method === 'DELETE' && String(u) === '/api/games/5')).toBe(false);
    });
  });

  describe('new game: search first', () => {
    const NOTHING = { results: [], bgg: 'used' };
    const search = (q: string, body: unknown) => ({ [`/parties/3/game-search?q=${encodeURIComponent(q)}`]: { body } });
    const type = async (value: string) => fireEvent.change(await screen.findByLabelText('Název'), { target: { value } });

    it('shows only the Name field at first, with no way to create a custom game yet', async () => {
      mockApi(routes);
      renderApp('/games/new');
      expect(await screen.findByRole('heading', { name: 'Přidat hru' })).toBeInTheDocument();
      expect(screen.getByLabelText('Název')).toBeInTheDocument();
      expect(screen.queryByLabelText('Zkrácený název')).not.toBeInTheDocument();
      expect(screen.queryByRole('button', { name: 'Uložit' })).not.toBeInTheDocument();
      expect(screen.queryByRole('button', { name: 'Založit vlastní hru' })).not.toBeInTheDocument();
      expect(screen.getByRole('link', { name: 'Zpět' })).toHaveAttribute('href', '/manage/games');
    });

    it('offers a custom game only after a search finds nothing, and prefills the name', async () => {
      const fetchMock = mockApi({ ...routes, ...search('Nová', NOTHING), 'POST /parties/3/games': { status: 201, body: FULL_GAME } });
      renderApp('/games/new');
      await type('Nová');
      fireEvent.click(await screen.findByRole('button', { name: 'Založit vlastní hru' }));

      expect(screen.getByLabelText('Název')).toHaveValue('Nová');
      expect(screen.getByText('Hra nemá rozšíření.')).toBeInTheDocument();
      expect(screen.getByText('Hra nemá postavy.')).toBeInTheDocument();
      expect(screen.getByText('Hra nemá vlastní hráče.')).toBeInTheDocument();
      fireEvent.click(screen.getByRole('button', { name: 'Uložit' }));
      await waitFor(() => expect(sent(fetchMock, 'POST', '/parties/3/games')).toMatchObject({ name: 'Nová', shortName: null, bggId: null, addons: [], characters: [], players: [] }));
    });

    it('lists results from every source: a game already in the party links to it, the rest are selectable', async () => {
      mockApi({
        ...routes,
        ...search('catan', {
          bgg: 'used',
          results: [
            { source: 'party', name: 'Catan vlastní', year: null, bggId: null, gameId: 8, imageUrl: '/uploads/covers/own.webp' },
            { source: 'catalog', name: 'Catan Junior', year: null, bggId: 40, gameId: 9, imageUrl: '/uploads/covers/junior.webp' },
            { source: 'cache', name: 'Catan', year: 1995, bggId: 13, gameId: null, imageUrl: null },
            { source: 'bgg', name: 'Catan: Seafarers', year: 1997, bggId: 14, gameId: null, imageUrl: null },
          ],
        }),
      });
      renderApp('/games/new');
      await type('catan');
      expect(await screen.findByRole('link', { name: /Catan vlastní/ })).toHaveAttribute('href', '/games/8');
      expect(screen.getByText('Už v partě')).toBeInTheDocument();
      expect(screen.getByRole('button', { name: /Catan Junior/ })).toBeInTheDocument();
      expect(screen.getByRole('button', { name: 'Catan (1995)' })).toBeInTheDocument();
      expect(screen.getByRole('button', { name: 'Catan: Seafarers (1997)' })).toBeInTheDocument();
      const covers = Array.from(screen.getByRole('list', { name: 'Nalezené hry' }).querySelectorAll('img'));
      expect(covers.map((img) => img.getAttribute('src'))).toEqual(['/uploads/covers/own.webp', '/uploads/covers/junior.webp', expect.stringContaining('cover-placeholder'), expect.stringContaining('cover-placeholder')]);
    });

    it('puts a catalog game straight into the party and opens it, without a form or a new game', async () => {
      const fetchMock = mockApi({
        ...routes,
        ...search('tok', {
          bgg: 'skipped',
          results: [{ source: 'catalog', name: 'Vládce Tokia', year: null, bggId: 123, gameId: 77, imageUrl: '/uploads/covers/abc.webp' }],
        }),
        'POST /parties/3/game-links': { status: 201, body: { ...FULL_GAME, id: 77, name: 'Vládce Tokia' } },
        '/parties/3/games/77': { body: { ...FULL_GAME, id: 77, name: 'Vládce Tokia' } },
        '/parties/3/plays?limit=11&offset=0&gameId=77': { body: [] },
      });
      renderApp('/games/new');
      await type('tok');
      expect(await screen.findByText('Z lokální databáze')).toBeInTheDocument();
      fireEvent.click(screen.getByRole('button', { name: /Vládce Tokia/ }));

      expect(await screen.findByRole('heading', { name: 'Vládce Tokia' })).toBeInTheDocument();
      expect(sent(fetchMock, 'POST', '/parties/3/game-links')).toEqual({ gameId: 77 });
      expect(requestedUrls(fetchMock).some((u) => u.includes('/bgg/import'))).toBe(false);
      expect(fetchMock.mock.calls.some(([u, init]) => init?.method === 'POST' && String(u) === '/api/parties/3/games')).toBe(false);
      expect(screen.queryByLabelText('Zkrácený název')).not.toBeInTheDocument();
    });

    it('says so when BoardGameGeek is unavailable but still lets the user create a custom game', async () => {
      mockApi({ ...routes, ...search('catan', { results: [], bgg: 'unavailable' }) });
      renderApp('/games/new');
      await type('catan');
      expect(await screen.findByText(/BoardGameGeek teď není dostupné/)).toBeInTheDocument();
      expect(screen.getByRole('button', { name: 'Založit vlastní hru' })).toBeInTheDocument();
    });

    it('prefills the form from the chosen BGG game and shows the CDN picture until it is cropped', async () => {
      stubUrls();
      const cdn = 'https://cf.geekdo-images.com/catan.jpg';
      const fetchMock = mockApi({
        ...routes,
        ...search('catan', { bgg: 'used', results: [{ source: 'bgg', name: 'Catan', year: 1995, bggId: 13, gameId: null, imageUrl: null }] }),
        'POST /parties/3/bgg/import': { body: { bggId: 13, name: 'Catan', bggImageUrl: cdn } },
        'POST /parties/3/game-images': { status: 201, body: { url: '/uploads/covers/abc.webp' } },
        'POST /parties/3/games': { status: 201, body: FULL_GAME },
      });
      renderApp('/games/new');
      await type('catan');
      fireEvent.click(await screen.findByRole('button', { name: 'Catan (1995)' }));

      // formulář je předvyplněný a hned se otevře ořez; ten čte obrázek přes náš server, ne přímo z CDN
      expect(await screen.findByRole('dialog', { name: 'Oříznout obrázek' })).toBeInTheDocument();
      expect(screen.getByLabelText('Název')).toHaveValue('Catan');
      expect(sent(fetchMock, 'POST', '/parties/3/bgg/import')).toEqual({ bggId: 13 });
      expect(cropToBlob).not.toHaveBeenCalled();

      // zrušení ořezu nechá živý náhled z CDN a nabídne ořez znovu; do hry se nic z CDN neuloží
      fireEvent.click(screen.getByRole('button', { name: 'Zrušit' }));
      expect(document.querySelector('img.game-image')).toHaveAttribute('src', cdn);
      expect(screen.getByText('Obrázek z BoardGameGeek se uloží až po oříznutí.')).toBeInTheDocument();
      fireEvent.click(screen.getByRole('button', { name: 'Oříznout obrázek z BGG' }));
      fireEvent.click(await screen.findByRole('button', { name: 'Nastavit výřez' }));
      fireEvent.click(screen.getByRole('button', { name: 'Použít' }));

      await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
      expect(vi.mocked(cropToBlob).mock.calls[0][0]).toBe(`/api/bgg/image?url=${encodeURIComponent(cdn)}`);
      expect(screen.queryByRole('button', { name: 'Oříznout obrázek z BGG' })).not.toBeInTheDocument();
      expect(document.querySelector('img.game-image')).toHaveAttribute('src', '/uploads/covers/abc.webp');
      expect(screen.getByRole('link', { name: 'BoardGameGeek' })).toHaveAttribute('href', 'https://boardgamegeek.com/boardgame/13');

      fireEvent.click(screen.getByRole('button', { name: 'Uložit' }));
      await waitFor(() => expect(sent(fetchMock, 'POST', '/parties/3/games')).toMatchObject({ name: 'Catan', bggId: 13, imageUrl: '/uploads/covers/abc.webp' }));
    });

    it('saves a BGG game without a picture when the user never crops it', async () => {
      stubUrls();
      const fetchMock = mockApi({
        ...routes,
        ...search('catan', { bgg: 'used', results: [{ source: 'bgg', name: 'Catan', year: 1995, bggId: 13, gameId: null, imageUrl: null }] }),
        'POST /parties/3/bgg/import': { body: { bggId: 13, name: 'Catan', bggImageUrl: 'https://cf.geekdo-images.com/catan.jpg' } },
        'POST /parties/3/games': { status: 201, body: FULL_GAME },
      });
      renderApp('/games/new');
      await type('catan');
      fireEvent.click(await screen.findByRole('button', { name: 'Catan (1995)' }));
      fireEvent.click(await screen.findByRole('button', { name: 'Zrušit' }));
      fireEvent.click(screen.getByRole('button', { name: 'Uložit' }));
      await waitFor(() => expect(sent(fetchMock, 'POST', '/parties/3/games')).toMatchObject({ name: 'Catan', bggId: 13, imageUrl: null }));
    });
  });

  describe('cover upload', () => {
    const pick = (file = new File(['x'], 'cover.png', { type: 'image/png' })) => {
      fireEvent.change(screen.getByLabelText('Soubor s obrázkem'), { target: { files: [file] } });
    };

    it('crops the chosen file in the editor, uploads the square and saves its address with the game', async () => {
      stubUrls();
      const fetchMock = mockApi({ ...routes, 'POST /parties/3/game-images': { status: 201, body: { url: '/uploads/covers/abc.webp' } }, 'PUT /games/5': { body: FULL_GAME } });
      renderApp('/games/5/edit');
      await waitFor(() => expect(screen.getByLabelText('Název')).toHaveValue('King of Tokyo'));

      pick();
      expect(await screen.findByRole('dialog', { name: 'Oříznout obrázek' })).toBeInTheDocument();
      expect(screen.getByRole('button', { name: 'Použít' })).toBeDisabled(); // dokud není výřez
      fireEvent.click(screen.getByRole('button', { name: 'Nastavit výřez' }));
      fireEvent.click(screen.getByRole('button', { name: 'Použít' }));

      await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
      const upload = fetchMock.mock.calls.find(([u, init]) => String(u) === '/api/parties/3/game-images' && init?.method === 'POST');
      expect(upload?.[1]?.body).toBeInstanceOf(FormData);
      expect((upload?.[1]?.body as FormData).get('file')).toBeInstanceOf(Blob);
      expect(screen.getByRole('button', { name: 'Změnit obrázek' })).toBeInTheDocument();

      fireEvent.click(screen.getByRole('button', { name: 'Uložit' }));
      await waitFor(() => expect(sent(fetchMock, 'PUT', '/games/5')?.imageUrl).toBe('/uploads/covers/abc.webp'));
    });

    it('cancelling the editor changes nothing, and the picture can be removed', async () => {
      stubUrls();
      const fetchMock = mockApi({ ...routes, '/parties/3/games/5': { body: { ...FULL_GAME, imageUrl: '/uploads/covers/0123456789abcdef0123456789abcdef01234567.webp' } }, 'PUT /games/5': { body: FULL_GAME } });
      renderApp('/games/5/edit');
      await waitFor(() => expect(screen.getByRole('button', { name: 'Změnit obrázek' })).toBeInTheDocument());
      expect(screen.queryByLabelText('URL obrázku')).not.toBeInTheDocument(); // adresa se ručně nezadává

      pick();
      fireEvent.click(await screen.findByRole('button', { name: 'Zrušit' }));
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
      expect(screen.getByRole('button', { name: 'Změnit obrázek' })).toBeInTheDocument();

      fireEvent.click(screen.getByRole('button', { name: 'Odebrat obrázek' }));
      fireEvent.click(screen.getByRole('button', { name: 'Uložit' }));
      await waitFor(() => expect(sent(fetchMock, 'PUT', '/games/5')?.imageUrl).toBeNull());
    });

    it('keeps the editor open and shows the server error when the upload is refused', async () => {
      stubUrls();
      mockApi({ ...routes, 'POST /parties/3/game-images': { status: 422, body: { error: 'Obrázek musí být čtvercový.' } } });
      renderApp('/games/5/edit');
      await waitFor(() => expect(screen.getByLabelText('Název')).toHaveValue('King of Tokyo'));
      pick();
      fireEvent.click(await screen.findByRole('button', { name: 'Nastavit výřez' }));
      fireEvent.click(screen.getByRole('button', { name: 'Použít' }));
      expect(await screen.findByText('Obrázek musí být čtvercový.')).toBeInTheDocument();
      expect(screen.getByRole('dialog')).toBeInTheDocument();
    });
  });
});
