import { screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { mockApi, ME, PARTY, renderApp } from '../test/helpers';

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
});
