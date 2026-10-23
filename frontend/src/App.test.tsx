import { fireEvent, screen, waitFor } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { GAME, mockApi, ME, PARTY, renderApp, requestedUrls } from './test/helpers';

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

  it('shows a splash instead of the empty-party prompt while parties fail to load', async () => {
    mockApi({ '/me': { body: ME }, '/parties': { status: 500, body: { error: 'boom' } } });
    renderApp();
    expect(await screen.findByText(/Server je nedostupný/)).toBeInTheDocument();
    expect(screen.queryByRole('link', { name: 'Vytvořit partu' })).not.toBeInTheDocument();
  });

  it('shows a loading splash (not the empty-party prompt) while parties are loading', async () => {
    let release: () => void = () => {};
    const gate = new Promise<void>((resolve) => { release = resolve; });
    const fetchMock = mockApi({ '/me': { body: ME }, '/parties': { body: [] } });
    const inner = fetchMock.getMockImplementation()!;
    fetchMock.mockImplementation(async (url: string, init?: RequestInit) => {
      if (url === '/api/parties') await gate;
      return inner(url, init);
    });
    renderApp();
    expect(await screen.findByText('Načítám…')).toBeInTheDocument();
    expect(screen.queryByRole('link', { name: 'Vytvořit partu' })).not.toBeInTheDocument();
    release();
    expect(await screen.findByRole('link', { name: 'Vytvořit partu' })).toBeInTheDocument();
  });

  it('keeps the invitation target through a StrictMode double render', async () => {
    sessionStorage.setItem('hesk.afterLogin', '/join/tok1');
    const fetchMock = mockApi({ '/me': { body: ME }, '/parties': { body: [PARTY] }, 'POST /invitations/tok1/accept': { body: PARTY } });
    renderApp('/', { strict: true });
    await waitFor(() => expect(requestedUrls(fetchMock)).toContain('/invitations/tok1/accept'));
    await waitFor(() => expect(sessionStorage.getItem('hesk.afterLogin')).toBeNull());
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

  it('keeps the user signed in and shows an error when logging out fails', async () => {
    mockApi({ '/me': { body: ME }, '/parties': { body: [PARTY] }, 'POST /auth/logout': { status: 500, body: { error: 'Odhlášení selhalo.' } }, '/parties/3/games?sort=activity&limit=21&offset=0': { body: [] } });
    renderApp();
    fireEvent.click(await screen.findByRole('button', { name: /Menu – Pátek/ }));
    fireEvent.click(screen.getByRole('button', { name: 'Odhlásit se' }));
    expect(await screen.findByText('Odhlášení selhalo.')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Odhlásit se' })).toBeEnabled();
  });

  it('closes the menu when the item of the current page is clicked (the address does not change)', async () => {
    mockApi({ '/me': { body: ME }, '/parties': { body: [PARTY] }, '/parties/3/games?sort=activity&limit=21&offset=0': { body: [] } });
    renderApp();
    await screen.findByRole('searchbox', { name: 'Hledat hru' });
    fireEvent.click(await screen.findByRole('button', { name: /Menu – Pátek/ }));
    const current = screen.getByRole('link', { name: 'Seznam her' });
    expect(current).toHaveClass('active');
    fireEvent.click(current);
    expect(screen.queryByRole('link', { name: 'Seznam her' })).not.toBeInTheDocument(); // menu je zavřené (skryté pro čtečky)
    expect(screen.getByRole('button', { name: /Menu – Pátek/ })).toHaveAttribute('aria-expanded', 'false');
  });

  it('moves the party management page under the newly selected party', async () => {
    const other = { id: 4, name: 'Víkend', shortName: 'Ví', role: 'admin' };
    const detail = (p: { id: number; name: string }) => ({ ...p, shortName: 'xx', role: 'admin', members: [{ userId: 1, nickname: 'Anna', role: 'admin' }] });
    mockApi({
      '/me': { body: ME },
      '/parties': { body: [PARTY, other] },
      '/parties/3': { body: detail(PARTY) },
      '/parties/4': { body: detail(other) },
    });
    renderApp('/parties/3');
    expect(await screen.findByRole('heading', { name: 'Parta Pátek' })).toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: /Menu – Pátek/ }));
    fireEvent.click(screen.getByRole('button', { name: 'Přepnout' }));
    fireEvent.click(screen.getByRole('button', { name: /Víkend/ }));
    expect(await screen.findByRole('heading', { name: 'Parta Víkend' })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Nastavení' })).toHaveAttribute('href', '/parties/4/edit');
  });

  it('switches the party in the menu and offers creating a new one like the old prototype', async () => {
    mockApi({
      '/me': { body: ME },
      '/parties': { body: [PARTY, { id: 4, name: 'Víkend', role: 'admin' }] },
      '/parties/3/games?sort=activity&limit=21&offset=0': { body: [] },
    });
    renderApp();
    await screen.findByRole('searchbox', { name: 'Hledat hru' }); // přesměrování / → /games zavírá menu, počkáme na něj
    fireEvent.click(await screen.findByRole('button', { name: /Menu – Pátek/ }));
    expect(await screen.findByText('Parta')).toBeInTheDocument();
    expect(screen.queryByText(/skupin/i)).not.toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Přepnout' }));
    expect(screen.getByRole('heading', { name: 'Vyber partu' })).toBeInTheDocument();
    expect(screen.queryByText('admin')).not.toBeInTheDocument();
    expect(screen.getByRole('button', { name: /Víkend/ })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Vytvořit novou partu' })).toHaveAttribute('href', '/parties');

    fireEvent.click(screen.getByRole('button', { name: 'Zrušit' }));
    expect(screen.getByRole('button', { name: 'Přepnout' })).toBeInTheDocument();
  });
});
