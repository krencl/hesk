import { fireEvent, screen, waitFor } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { GAME, mockApi, ME, PARTY, renderApp, requestedUrls } from '../test/helpers';

afterEach(() => { vi.unstubAllGlobals(); sessionStorage.clear(); });

describe('statistics', () => {
  it('uses the short name of a game when it has one', async () => {
    mockApi({
      '/me': { body: ME },
      '/parties': { body: [PARTY] },
      '/parties/3/stats': {
        body: {
          wins: [{ playerId: 1, name: 'Anna', wins: 3, plays: 5 }],
          topGames: [{ gameId: 1, name: 'Ticket to Ride: Evropa', shortName: 'TtR Evropa', plays: 7 }, { gameId: 2, name: 'Azul', shortName: null, plays: 4 }],
          starters: [{ playerId: 1, name: 'Anna', starts: 4 }, { playerId: 2, name: 'Petr', starts: 2 }],
          playTags: [{ name: 'Skalka', plays: 6 }, { name: 'Horoušánky', plays: 3 }],
        },
      },
    });
    renderApp('/stats');
    expect(await screen.findByText('TtR Evropa')).toBeInTheDocument();
    expect(screen.getByText('Azul')).toBeInTheDocument();
    expect(screen.queryByText('Ticket to Ride: Evropa')).not.toBeInTheDocument();
  });

  it('shows who started most often and the most frequent play tags', async () => {
    mockApi({
      '/me': { body: ME },
      '/parties': { body: [PARTY] },
      '/parties/3/stats': {
        body: {
          wins: [{ playerId: 1, name: 'Anna', wins: 3, plays: 5 }],
          topGames: [{ gameId: 2, name: 'Azul', shortName: null, plays: 4 }],
          starters: [{ playerId: 1, name: 'Anna', starts: 4 }, { playerId: 2, name: 'Petr', starts: 2 }],
          playTags: [{ name: 'Skalka', plays: 6 }, { name: 'Horoušánky', plays: 3 }],
        },
      },
    });
    renderApp('/parties/3/stats');
    expect(await screen.findByRole('heading', { name: 'Nejčastější začínající' })).toBeInTheDocument();
    expect(screen.getByRole('img', { name: 'Petr: 2' })).toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'Nejčastější tagy' })).toBeInTheDocument();
    expect(screen.getByRole('img', { name: 'Skalka: 6' })).toBeInTheDocument();
    expect(screen.getByRole('img', { name: 'Horoušánky: 3' })).toBeInTheDocument();
  });

  it('shows just a sentence when the party has not played anything', async () => {
    mockApi({
      '/me': { body: ME },
      '/parties': { body: [PARTY] },
      '/parties/3/stats': { body: { wins: [], topGames: [], starters: [], playTags: [] } },
    });
    renderApp('/stats');
    expect(await screen.findByText('Ještě jste nic neodehráli, je čas to napravit.')).toBeInTheDocument();
    expect(screen.queryByText('Výhry hráčů')).not.toBeInTheDocument();
    expect(screen.queryByText('Nejhranější hry')).not.toBeInTheDocument();
  });

  it('filters by searched games and tags: picked ones start as "+", a click turns them into "−"', async () => {
    const empty = { body: { wins: [], topGames: [], starters: [], playTags: [] } };
    const fetchMock = mockApi({
      '/me': { body: ME },
      '/parties': { body: [PARTY] },
      '/parties/3/game-tags': { body: ['Rychlovka', 'Strategie'] },
      '/parties/3/games?q=azu&sort=name&limit=20': { body: [{ ...GAME, id: 2, name: 'Azul' }] },
      '/parties/3/stats': { body: { wins: [{ playerId: 1, name: 'Anna', wins: 3, plays: 5 }], topGames: [{ gameId: 2, name: 'Azul', shortName: null, plays: 4 }], starters: [], playTags: [] } },
      '/parties/3/stats?tags%5B%5D=Strategie': { body: { wins: [], topGames: [{ gameId: 3, name: 'Caverna', shortName: null, plays: 2 }], starters: [], playTags: [] } },
      '/parties/3/stats?excludeTags%5B%5D=Strategie': empty,
      '/parties/3/stats?excludeTags%5B%5D=Strategie&games%5B%5D=2': empty,
    });
    renderApp('/stats');
    expect(await screen.findByText('Azul')).toBeInTheDocument();
    const search = screen.getByLabelText('Hledat hru nebo tag');

    // tag: najde se bez ohledu na diakritiku a velikost písmen, vybere se jako „+“
    fireEvent.change(search, { target: { value: 'STRAT' } });
    fireEvent.click(await screen.findByRole('button', { name: /^Strategie/ }));
    expect(await screen.findByRole('button', { name: 'Strategie: jen s ním' })).toHaveTextContent('+ Strategie');
    expect(search).toHaveValue('');
    expect(await screen.findByText('Caverna')).toBeInTheDocument();

    // klepnutí na štítek ho změní na „−“; nic neodpovídá, filtr zůstává
    fireEvent.click(screen.getByRole('button', { name: 'Strategie: jen s ním' }));
    expect(await screen.findByRole('button', { name: 'Strategie: bez něj' })).toHaveTextContent('− Strategie');
    expect(await screen.findByText('Žádná odehraná hra neodpovídá filtru.')).toBeInTheDocument();

    // hra se hledá ve hrách party (výsledky nahradí grafy) a přidá se k tagu
    fireEvent.change(search, { target: { value: 'azu' } });
    fireEvent.click(await screen.findByRole('button', { name: /^Azul/ }));
    expect(await screen.findByRole('button', { name: 'Azul: jen s ním' })).toHaveTextContent('+ Azul');
    await waitFor(() => expect(requestedUrls(fetchMock)).toContain('/parties/3/stats?excludeTags%5B%5D=Strategie&games%5B%5D=2'));

    // křížek vybranou položku odebere
    fireEvent.click(screen.getByRole('button', { name: 'Odebrat Azul z filtru' }));
    expect(screen.queryByRole('button', { name: 'Azul: jen s ním' })).not.toBeInTheDocument();
  });

  it('offers all the tags right away when the empty search field gets the focus', async () => {
    const fetchMock = mockApi({
      '/me': { body: ME },
      '/parties': { body: [PARTY] },
      '/parties/3/game-tags': { body: ['Rychlovka', 'Strategie'] },
      '/parties/3/stats': { body: { wins: [{ playerId: 1, name: 'Anna', wins: 3, plays: 5 }], topGames: [{ gameId: 2, name: 'Azul', shortName: null, plays: 4 }], starters: [], playTags: [] } },
      '/parties/3/stats?tags%5B%5D=Rychlovka': { body: { wins: [], topGames: [{ gameId: 3, name: 'Caverna', shortName: null, plays: 2 }], starters: [], playTags: [] } },
    });
    renderApp('/stats');
    expect(await screen.findByText('Azul')).toBeInTheDocument();
    const search = screen.getByLabelText('Hledat hru nebo tag');
    expect(screen.queryByRole('button', { name: /^Rychlovka/ })).not.toBeInTheDocument();

    fireEvent.focus(search);
    expect(await screen.findByRole('button', { name: /^Rychlovka/ })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /^Strategie/ })).toBeInTheDocument();
    expect(screen.queryByText('Azul')).not.toBeInTheDocument();

    // výběr nechá nabídku otevřenou, jen už bez vybraného tagu
    fireEvent.click(screen.getByRole('button', { name: /^Rychlovka/ }));
    expect(await screen.findByRole('button', { name: 'Rychlovka: jen s ním' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /^Strategie/ })).toBeInTheDocument();
    await waitFor(() => expect(requestedUrls(fetchMock)).toContain('/parties/3/stats?tags%5B%5D=Rychlovka'));

    // po opuštění pole se vrátí grafy
    fireEvent.blur(search);
    expect(await screen.findByText('Caverna')).toBeInTheDocument();
  });

  it('has + and − buttons on the rows of the search results; the whole row works as "+"', async () => {
    mockApi({
      '/me': { body: ME },
      '/parties': { body: [PARTY] },
      '/parties/3/game-tags': { body: ['Rychlovka', 'Strategie'] },
      '/parties/3/games?q=azu&sort=name&limit=20': { body: [{ ...GAME, id: 2, name: 'Azul' }] },
      '/parties/3/stats': { body: { wins: [], topGames: [{ gameId: 2, name: 'Azul', shortName: null, plays: 4 }], starters: [], playTags: [] } },
      '/parties/3/stats?excludeTags%5B%5D=Rychlovka': { body: { wins: [], topGames: [{ gameId: 2, name: 'Azul', shortName: null, plays: 4 }], starters: [], playTags: [] } },
      '/parties/3/stats?excludeTags%5B%5D=Rychlovka&games%5B%5D=2': { body: { wins: [], topGames: [{ gameId: 2, name: 'Azul', shortName: null, plays: 4 }], starters: [], playTags: [] } },
    });
    renderApp('/stats');
    expect(await screen.findByText('Azul')).toBeInTheDocument();
    const search = screen.getByLabelText('Hledat hru nebo tag');

    fireEvent.focus(search);
    fireEvent.click(await screen.findByRole('button', { name: 'Přidat Rychlovka (vyřadit)' }));
    expect(await screen.findByRole('button', { name: 'Rychlovka: bez něj' })).toHaveTextContent('− Rychlovka');

    fireEvent.change(search, { target: { value: 'azu' } });
    fireEvent.click(await screen.findByRole('button', { name: 'Přidat Azul (jen s ním)' }));
    expect(await screen.findByRole('button', { name: 'Azul: jen s ním' })).toHaveTextContent('+ Azul');
  });

  it('lives under /parties/:id/stats, takes the filter from the address and can clear it', async () => {
    const fetchMock = mockApi({
      '/me': { body: ME },
      '/parties': { body: [PARTY] },
      '/parties/3/game-tags': { body: ['Rychlovka', 'Strategie'] },
      '/parties/3/games/2': { body: { ...GAME, id: 2, name: 'Azul' } },
      '/parties/3/stats': { body: { wins: [], topGames: [{ gameId: 2, name: 'Azul', shortName: null, plays: 4 }, { gameId: 3, name: 'Caverna', shortName: null, plays: 2 }], starters: [], playTags: [] } },
      '/parties/3/stats?tags%5B%5D=Rychlovka&excludeTags%5B%5D=Strategie&games%5B%5D=2': { body: { wins: [], topGames: [{ gameId: 2, name: 'Azul', shortName: null, plays: 4 }], starters: [], playTags: [] } },
    });
    renderApp('/parties/3/stats?games=2&tags=Rychlovka&excludeTags=Strategie');

    expect(await screen.findByRole('button', { name: 'Azul: jen s ním' })).toHaveTextContent('+ Azul');
    expect(screen.getByRole('button', { name: 'Rychlovka: jen s ním' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Strategie: bez něj' })).toBeInTheDocument();
    expect(requestedUrls(fetchMock)).toContain('/parties/3/stats?tags%5B%5D=Rychlovka&excludeTags%5B%5D=Strategie&games%5B%5D=2');
    expect(screen.queryByText('Caverna')).not.toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Zrušit filtr' }));
    expect(await screen.findByText('Caverna')).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Zrušit filtr' })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Azul: jen s ním' })).not.toBeInTheDocument();
  });

  it('redirects the old /stats to the active party and refuses a party the user is not in', async () => {
    mockApi({
      '/me': { body: ME },
      '/parties': { body: [PARTY] },
      '/parties/3/stats': { body: { wins: [], topGames: [{ gameId: 2, name: 'Azul', shortName: null, plays: 4 }], starters: [], playTags: [] } },
    });
    renderApp('/stats');
    expect(await screen.findByRole('heading', { name: /Pátek/ })).toBeInTheDocument();
    expect(await screen.findByText('Azul')).toBeInTheDocument();
  });

  it('shows a message for a party that is not among the user\'s parties', async () => {
    mockApi({ '/me': { body: ME }, '/parties': { body: [PARTY] } });
    renderApp('/parties/99/stats');
    expect(await screen.findByText('Tuhle partu nevidíš, nebo neexistuje.')).toBeInTheDocument();
  });

  it('closes the search results with the Zavřít button under them', async () => {
    mockApi({
      '/me': { body: ME },
      '/parties': { body: [PARTY] },
      '/parties/3/game-tags': { body: ['Rychlovka'] },
      '/parties/3/stats': { body: { wins: [], topGames: [{ gameId: 2, name: 'Azul', shortName: null, plays: 4 }], starters: [], playTags: [] } },
    });
    renderApp('/parties/3/stats');
    expect(await screen.findByText('Azul')).toBeInTheDocument();
    const search = screen.getByLabelText('Hledat hru nebo tag');
    expect(screen.queryByRole('button', { name: 'Zavřít' })).not.toBeInTheDocument();

    search.focus();
    fireEvent.change(search, { target: { value: 'ryc' } });
    expect(await screen.findByRole('button', { name: /^Rychlovka/ })).toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: 'Zavřít' }));

    expect(search).toHaveValue('');
    expect(search).not.toHaveFocus();
    expect(screen.queryByRole('button', { name: /^Rychlovka/ })).not.toBeInTheDocument();
    expect(await screen.findByText('Azul')).toBeInTheDocument();
  });
});
