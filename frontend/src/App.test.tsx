import { screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { GAME, mockApi, ME, PARTY, renderApp } from './test/helpers';

afterEach(() => { vi.unstubAllGlobals(); sessionStorage.clear(); });

describe('App gating', () => {
  it('shows login for anonymous users and remembers invitation path', async () => {
    mockApi({ '/me': { status: 401, body: { error: 'Unauthorized' } } });
    renderApp('/join/tok123');
    expect(await screen.findByRole('link', { name: /Google/ })).toBeInTheDocument();
    expect(sessionStorage.getItem('hesk.afterLogin')).toBe('/join/tok123');
  });

  it('forces setting a nickname first', async () => {
    mockApi({ '/me': { body: { id: 1, nickname: null, email: 'a@b.cz', hasNickname: false } } });
    renderApp();
    expect(await screen.findByRole('heading', { name: 'Jak ti máme říkat?' })).toBeInTheDocument();
  });

  it('offers creating a party when user has none', async () => {
    mockApi({
      '/me': { body: { id: 1, nickname: 'Anna', email: 'a@b.cz', hasNickname: true } },
      '/parties': { body: [] },
    });
    renderApp();
    expect(await screen.findByRole('link', { name: 'Vytvořit skupinu' })).toBeInTheDocument();
  });

  it('renders the active party in the top bar and the game list as the home page', async () => {
    mockApi({
      '/me': { body: ME },
      '/parties': { body: [PARTY] },
      '/parties/3/games?sort=activity&limit=21&offset=0': { body: [GAME] },
    });
    renderApp();
    expect(await screen.findByRole('button', { name: /Menu – Pátek/ })).toBeInTheDocument();
    expect(await screen.findByText('Carcassonne')).toBeInTheDocument();
    expect(screen.getByText(/6\.2\.2022 \d{2}:\d{2} #69/)).toBeInTheDocument();
    expect(screen.getByText('Anna')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: /Carcassonne/ })).toHaveAttribute('href', '/games/1');
  });

  it('shows the detail of a play with results', async () => {
    mockApi({
      '/me': { body: { id: 1, nickname: 'Anna', email: 'a@b.cz', hasNickname: true } },
      '/parties': { body: [{ id: 3, name: 'Pátek', role: 'admin' }] },
      '/plays/9': {
        body: {
          id: 9, sessionId: 1, sessionNumber: 1, game: { id: 1, name: 'Carcassonne', imageUrl: null, scoreType: 'most_points' },
          startedAt: '2026-03-01T18:00:00+00:00', endedAt: '2026-03-01T19:30:00+00:00', place: 'U Kuby', notes: 'Skvělá hra',
          addons: [{ id: 1, name: 'Řeka' }], tags: ['rodinná'],
          results: [
            { playerId: 1, name: 'Anna', score: 90, rank: 1, winner: true },
            { playerId: 2, name: 'Petr', score: 75, rank: 2, winner: false },
          ],
        },
      },
    });
    renderApp('/plays/9');
    expect(await screen.findByText('Carcassonne')).toBeInTheDocument();
    expect(screen.getByText(/1 h 30 min/)).toBeInTheDocument();
    expect(screen.getByText('U Kuby')).toBeInTheDocument();
    expect(screen.getByText('Řeka')).toBeInTheDocument();
    expect(screen.getByText('#rodinná')).toBeInTheDocument();
    expect(screen.getByText('Skvělá hra')).toBeInTheDocument();
    const rows = screen.getAllByRole('listitem', { name: '' }).filter((li) => li.closest('.results'));
    expect(rows).toHaveLength(2);
    expect(rows[0]).toHaveTextContent('Anna');
    expect(rows[0]).toHaveTextContent('90');
    expect(rows[0]).toHaveClass('winner');
    expect(rows[1]).not.toHaveClass('winner');
  });
});
