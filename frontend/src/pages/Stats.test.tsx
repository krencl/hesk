import { fireEvent, screen, waitFor } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { mockApi, ME, PARTY, renderApp, requestedUrls } from '../test/helpers';

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

  it('filters the statistics by the tags of games and keeps the filter when nothing matches', async () => {
    const fetchMock = mockApi({
      '/me': { body: ME },
      '/parties': { body: [PARTY] },
      '/parties/3/game-tags': { body: ['Rychlovka', 'Strategie'] },
      '/parties/3/stats': { body: { wins: [{ playerId: 1, name: 'Anna', wins: 3, plays: 5 }], topGames: [{ gameId: 2, name: 'Azul', shortName: null, plays: 4 }] } },
      '/parties/3/stats?tags%5B%5D=Strategie': { body: { wins: [], topGames: [] } },
      '/parties/3/stats?tags%5B%5D=Strategie&excludeTags%5B%5D=Rychlovka': { body: { wins: [], topGames: [] } },
    });
    renderApp('/stats');
    expect(await screen.findByText('Azul')).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Strategie: bez filtru' }));
    expect(await screen.findByText('Žádná odehraná hra neodpovídá filtru.')).toBeInTheDocument();
    expect(screen.queryByText('Ještě jste nic neodehráli, je čas to napravit.')).not.toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Rychlovka: bez filtru' }));
    fireEvent.click(screen.getByRole('button', { name: 'Rychlovka: jen s tagem' }));
    await waitFor(() => expect(requestedUrls(fetchMock)).toContain('/parties/3/stats?tags%5B%5D=Strategie&excludeTags%5B%5D=Rychlovka'));
    expect(screen.getByRole('button', { name: 'Rychlovka: bez tagu' })).toBeInTheDocument();
  });
});
