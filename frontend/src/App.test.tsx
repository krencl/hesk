import { fireEvent, screen } from '@testing-library/react';
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
    expect(await screen.findByRole('link', { name: 'Vytvořit partu' })).toBeInTheDocument();
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

  it('switches the party in the menu and offers creating a new one like the old prototype', async () => {
    mockApi({
      '/me': { body: ME },
      '/parties': { body: [PARTY, { id: 4, name: 'Víkend', role: 'member' }] },
      '/parties/3/games?sort=activity&limit=21&offset=0': { body: [] },
    });
    renderApp();
    await screen.findByRole('searchbox', { name: 'Hledat hru' }); // přesměrování / → /games zavírá menu, počkáme na něj
    fireEvent.click(await screen.findByRole('button', { name: /Menu – Pátek/ }));
    expect(await screen.findByText('Parta')).toBeInTheDocument();
    expect(screen.queryByText(/skupin/i)).not.toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Přepnout' }));
    expect(screen.getByRole('heading', { name: 'Vyber partu' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /Víkend/ })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Vytvořit novou partu' })).toHaveAttribute('href', '/parties');

    fireEvent.click(screen.getByRole('button', { name: 'Zrušit' }));
    expect(screen.getByRole('button', { name: 'Přepnout' })).toBeInTheDocument();
  });
});
