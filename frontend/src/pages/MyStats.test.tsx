import { screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { GAME, mockApi, ME, PARTY, renderApp } from '../test/helpers';

afterEach(() => vi.restoreAllMocks());

const base = {
  '/me': { body: ME },
  '/parties': { body: [PARTY] },
  '/parties/3/games?sort=activity&limit=21&offset=0': { body: [GAME] },
};

describe('my stats (/my-stats)', () => {
  it('is in the menu below the game list', async () => {
    mockApi({ ...base, '/parties/3/stats/me': { body: { player: null, plays: 0, wins: 0, topGames: [], gameTags: [] } } });
    renderApp('/');
    const list = await screen.findByRole('link', { name: 'Seznam her', hidden: true });
    const mine = screen.getByRole('link', { name: 'Moje statistiky', hidden: true });
    expect(mine).toHaveAttribute('href', '/my-stats');
    expect(list.compareDocumentPosition(mine) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
  });

  it('lists the games with the most wins and the wins by game tag', async () => {
    mockApi({
      ...base,
      '/parties/3/stats/me': {
        body: {
          player: { id: 1, name: 'Anna' }, plays: 40, wins: 21,
          topGames: [{ gameId: 1, name: 'Carcassonne', shortName: null, wins: 16, plays: 28 }, { gameId: 2, name: 'Caverna', shortName: null, wins: 5, plays: 12 }],
          gameTags: [{ name: 'Strategie', wins: 15, plays: 30 }, { name: 'Párty', wins: 6, plays: 10 }],
        },
      },
    });
    renderApp('/my-stats');
    expect(await screen.findByRole('heading', { name: 'Nejvíc výher' })).toBeInTheDocument();
    expect(screen.getByText('Anna: 21 výher z 40 her')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Carcassonne' })).toHaveAttribute('href', '/games/1');
    expect(screen.getByText('16 výher z 28')).toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'Výhry podle tagu hry' })).toBeInTheDocument();
    expect(screen.getByRole('img', { name: 'Strategie: 15' })).toBeInTheDocument();
    expect(screen.getByRole('img', { name: 'Párty: 6' })).toBeInTheDocument();
  });

  it('says so when the player has no win yet', async () => {
    mockApi({ ...base, '/parties/3/stats/me': { body: { player: { id: 1, name: 'Anna' }, plays: 2, wins: 0, topGames: [], gameTags: [] } } });
    renderApp('/my-stats');
    expect(await screen.findByText(/Zatím tu nemáš žádnou výhru/)).toBeInTheDocument();
  });
});
