import { fireEvent, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { GAME, mockApi, ME, PARTY, renderApp, requestedUrls } from '../test/helpers';

afterEach(() => { vi.unstubAllGlobals(); sessionStorage.clear(); });

const base = { '/me': { body: ME }, '/parties': { body: [PARTY] } };
const games = (n: number, from = 0) => Array.from({ length: n }, (_, i) => ({ ...GAME, id: from + i + 1, name: `Hra ${from + i + 1}` }));

describe('game management (/manage/games)', () => {
  it('lists games by name, links to their edit form and offers creating a new one', async () => {
    const fetchMock = mockApi({ ...base, '/parties/3/games?sort=name&limit=21&offset=0': { body: [GAME] } });
    renderApp('/manage/games');
    expect(await screen.findByRole('heading', { name: 'Správa her' })).toBeInTheDocument();
    expect(await screen.findByRole('link', { name: /Carcassonne/ })).toHaveAttribute('href', '/games/1/edit');
    expect(screen.getByText('Nejvíc bodů vyhrává')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Nová hra' })).toHaveAttribute('href', '/games/new');
    expect(requestedUrls(fetchMock)).toContain('/parties/3/games?sort=name&limit=21&offset=0');
  });

  it('filters by name and says so when nothing is found', async () => {
    const fetchMock = mockApi({
      ...base,
      '/parties/3/games?sort=name&limit=21&offset=0': { body: [GAME] },
      '/parties/3/games?q=xyz&sort=name&limit=21&offset=0': { body: [] },
    });
    renderApp('/manage/games');
    await screen.findByText('Carcassonne');
    fireEvent.change(screen.getByRole('searchbox', { name: 'Hledat hru' }), { target: { value: 'xyz' } });
    expect(await screen.findByText(/nic nenašlo/)).toBeInTheDocument();
    expect(requestedUrls(fetchMock)).toContain('/parties/3/games?q=xyz&sort=name&limit=21&offset=0');
  });

  it('has a filter with sorting only (no session filter) that is remembered separately from the game list', async () => {
    const fetchMock = mockApi({
      ...base,
      '/parties/3/games?sort=name&limit=21&offset=0': { body: [GAME] },
      '/parties/3/games?sort=activity&limit=21&offset=0': { body: [GAME] },
    });
    renderApp('/manage/games');
    await screen.findByText('Carcassonne');
    fireEvent.click(screen.getByRole('button', { name: 'Filtr' }));
    expect(screen.getByRole('button', { name: 'Řazení' })).toHaveTextContent('Název');
    expect(screen.queryByRole('button', { name: 'Sezení' })).not.toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: 'Řazení' }));
    fireEvent.click(screen.getByRole('option', { name: 'Aktivita' }));
    await screen.findByText('Carcassonne');
    expect(requestedUrls(fetchMock)).toContain('/parties/3/games?sort=activity&limit=21&offset=0');
    expect(JSON.parse(sessionStorage.getItem('hesk.manageGames')!)).toMatchObject({ sort: 'activity', filterVisible: true });
    expect(sessionStorage.getItem('hesk.gameList')).toBeNull();
  });

  it('loads further pages (button fallback without IntersectionObserver)', async () => {
    vi.stubGlobal('IntersectionObserver', undefined);
    const fetchMock = mockApi({
      ...base,
      '/parties/3/games?sort=name&limit=21&offset=0': { body: [...games(20), { ...GAME, id: 99, name: 'Dvacátá první' }] },
      '/parties/3/games?sort=name&limit=21&offset=20': { body: [{ ...GAME, id: 99, name: 'Dvacátá první' }] },
    });
    renderApp('/manage/games');
    expect(await screen.findByText('Hra 20')).toBeInTheDocument();
    expect(screen.queryByText('Dvacátá první')).not.toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: 'Načíst další' }));
    expect(await screen.findByText('Dvacátá první')).toBeInTheDocument();
    expect(requestedUrls(fetchMock)).toContain('/parties/3/games?sort=name&limit=21&offset=20');
  });
});
