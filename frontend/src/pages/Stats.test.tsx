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
        },
      },
    });
    renderApp('/stats');
    expect(await screen.findByText('TtR Evropa')).toBeInTheDocument();
    expect(screen.getByText('Azul')).toBeInTheDocument();
    expect(screen.queryByText('Ticket to Ride: Evropa')).not.toBeInTheDocument();
  });

  it('shows just a sentence when the party has not played anything', async () => {
    mockApi({
      '/me': { body: ME },
      '/parties': { body: [PARTY] },
      '/parties/3/stats': { body: { wins: [], topGames: [] } },
    });
    renderApp('/stats');
    expect(await screen.findByText('Ještě jste nic neodehráli, je čas to napravit.')).toBeInTheDocument();
    expect(screen.queryByText('Výhry hráčů')).not.toBeInTheDocument();
    expect(screen.queryByText('Nejhranější hry')).not.toBeInTheDocument();
  });

  it('filters by searched games and tags: picked ones start as "+", a click turns them into "−"', async () => {
    const empty = { body: { wins: [], topGames: [] } };
    const fetchMock = mockApi({
      '/me': { body: ME },
      '/parties': { body: [PARTY] },
      '/parties/3/game-tags': { body: ['Rychlovka', 'Strategie'] },
      '/parties/3/games?q=azu&sort=name&limit=20': { body: [{ ...GAME, id: 2, name: 'Azul' }] },
      '/parties/3/stats': { body: { wins: [{ playerId: 1, name: 'Anna', wins: 3, plays: 5 }], topGames: [{ gameId: 2, name: 'Azul', shortName: null, plays: 4 }] } },
      '/parties/3/stats?tags%5B%5D=Strategie': { body: { wins: [], topGames: [{ gameId: 3, name: 'Caverna', shortName: null, plays: 2 }] } },
      '/parties/3/stats?excludeTags%5B%5D=Strategie': empty,
      '/parties/3/stats?excludeTags%5B%5D=Strategie&games%5B%5D=2': empty,
    });
    renderApp('/stats');
    expect(await screen.findByText('Azul')).toBeInTheDocument();
    const search = screen.getByLabelText('Hledat hru nebo tag');

    // tag: najde se bez ohledu na diakritiku a velikost písmen, vybere se jako „+“
    fireEvent.change(search, { target: { value: 'STRAT' } });
    fireEvent.click(await screen.findByRole('button', { name: /Strategie/ }));
    expect(await screen.findByRole('button', { name: 'Strategie: jen s ním' })).toHaveTextContent('+ Strategie');
    expect(search).toHaveValue('');
    expect(await screen.findByText('Caverna')).toBeInTheDocument();

    // klepnutí na štítek ho změní na „−“; nic neodpovídá, filtr zůstává
    fireEvent.click(screen.getByRole('button', { name: 'Strategie: jen s ním' }));
    expect(await screen.findByRole('button', { name: 'Strategie: bez něj' })).toHaveTextContent('− Strategie');
    expect(await screen.findByText('Žádná odehraná hra neodpovídá filtru.')).toBeInTheDocument();

    // hra se hledá ve hrách party (výsledky nahradí grafy) a přidá se k tagu
    fireEvent.change(search, { target: { value: 'azu' } });
    fireEvent.click(await screen.findByRole('button', { name: /Azul/ }));
    expect(await screen.findByRole('button', { name: 'Azul: jen s ním' })).toHaveTextContent('+ Azul');
    await waitFor(() => expect(requestedUrls(fetchMock)).toContain('/parties/3/stats?excludeTags%5B%5D=Strategie&games%5B%5D=2'));

    // křížek vybranou položku odebere
    fireEvent.click(screen.getByRole('button', { name: 'Odebrat Azul z filtru' }));
    expect(screen.queryByRole('button', { name: 'Azul: jen s ním' })).not.toBeInTheDocument();
  });
});
