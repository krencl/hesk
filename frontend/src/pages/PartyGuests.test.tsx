import { fireEvent, screen, waitFor, within } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { GAME, mockApi, ME, PARTY, renderApp, sentBody } from '../test/helpers';

afterEach(() => vi.restoreAllMocks());

const detail = { ...PARTY, members: [{ userId: 1, nickname: 'Anna', role: 'admin' }, { userId: 2, nickname: 'Viki', role: 'writer' }] };
const PLAYERS = [
  { id: 1, name: 'Anna', hasAccount: true, visible: true, playCount: 12 },
  { id: 2, name: 'Bob', hasAccount: false, visible: true, playCount: 3 },
  { id: 3, name: 'Cyril', hasAccount: false, visible: false, playCount: 0 },
];
const base = {
  '/me': { body: ME },
  '/parties': { body: [PARTY] },
  '/parties/3': { body: detail },
  '/parties/3/players': { body: PLAYERS },
  '/parties/3/games?sort=activity&limit=21&offset=0': { body: [GAME] },
};

describe('party guests (/parties/:id/guests)', () => {
  it('does not list game players (they belong to the game, not to guest management)', async () => {
    mockApi({ ...base, '/parties/3/players': { body: [...PLAYERS, { id: 4, name: 'Boss', hasAccount: false, visible: true, playCount: 5, gamePlayer: true }] } });
    renderApp('/parties/3/guests');
    expect(await screen.findByLabelText('Jméno hosta Bob')).toBeInTheDocument();
    expect(screen.queryByLabelText('Jméno hosta Boss')).not.toBeInTheDocument();
  });

  it('lists only guests with their play counts; deleting is offered only for unused ones', async () => {
    mockApi(base);
    renderApp('/parties/3/guests');
    expect(await screen.findByLabelText('Jméno hosta Bob')).toHaveValue('Bob');
    expect(screen.getByLabelText('Jméno hosta Cyril')).toHaveValue('Cyril');
    expect(screen.queryByLabelText('Jméno hosta Anna')).not.toBeInTheDocument(); // člen s účtem není host
    expect(screen.getByRole('link', { name: 'Hosté' })).toHaveClass('active');
    expect(screen.getByText('3 zápisy')).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Smazat hosta Bob' })).not.toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Smazat hosta Cyril' })).toBeInTheDocument();
  });

  it('renames changed guests only and enables saving just when something changed', async () => {
    const fetchMock = mockApi({ ...base, 'PATCH /players/2': { body: { id: 2, name: 'Bobík', hasAccount: false, visible: true } } });
    renderApp('/parties/3/guests');
    fireEvent.change(await screen.findByLabelText('Jméno hosta Bob'), { target: { value: ' Bobík ' } });
    const save = screen.getByRole('button', { name: 'Uložit' });
    expect(save).toBeEnabled();
    fireEvent.click(save);

    await waitFor(() => expect(sentBody(fetchMock, 'PATCH', '/players/2')).toEqual({ name: 'Bobík' }));
    expect(fetchMock.mock.calls.filter(([, init]) => init?.method === 'PATCH')).toHaveLength(1);
  });

  it('toggles whether a guest is offered in plays and saves just that change', async () => {
    const fetchMock = mockApi({ ...base, 'PATCH /players/2': { body: { id: 2, name: 'Bob', hasAccount: false, visible: false } }, 'PATCH /players/3': { body: { id: 3, name: 'Cyril', hasAccount: false, visible: true } } });
    renderApp('/parties/3/guests');
    const bob = await screen.findByRole('button', { name: 'Zobrazovat hosta Bob' });
    const cyril = screen.getByRole('button', { name: 'Zobrazovat hosta Cyril' });
    expect(bob).toHaveAttribute('aria-pressed', 'true');
    expect(cyril).toHaveAttribute('aria-pressed', 'false'); // skrytý host
    // stejné tlačítko jako koš, jen s okem (přeškrtnuté = skrytý)
    expect(bob).toHaveClass('icon-button');
    expect(bob).toHaveTextContent('visibility');
    expect(cyril).toHaveTextContent('visibility_off');
    const save = screen.getByRole('button', { name: 'Uložit' });
    expect(save).toBeDisabled();

    fireEvent.click(bob);
    expect(save).toBeEnabled();
    fireEvent.click(bob); // vrácení na původní hodnotu není změna
    expect(save).toBeDisabled();

    fireEvent.click(bob);
    fireEvent.click(cyril);
    fireEvent.click(save);
    await waitFor(() => expect(fetchMock.mock.calls.filter(([, init]) => init?.method === 'PATCH')).toHaveLength(2));
    expect(sentBody(fetchMock, 'PATCH', '/players/2')).toEqual({ visible: false });
    expect(sentBody(fetchMock, 'PATCH', '/players/3')).toEqual({ visible: true });
  });

  it('has "Zrušit" as the secondary button, closing the whole party management', async () => {
    mockApi(base);
    renderApp('/parties/3/guests');
    await screen.findByLabelText('Jméno hosta Bob');
    expect(screen.getByRole('link', { name: 'Zrušit' })).toHaveAttribute('href', '/');
  });

  it('keeps Save disabled while nothing is changed', async () => {
    mockApi(base);
    renderApp('/parties/3/guests');
    await screen.findByLabelText('Jméno hosta Bob');
    expect(screen.getByRole('button', { name: 'Uložit' })).toBeDisabled();
  });

  it('adds a new guest, also with Enter', async () => {
    const fetchMock = mockApi({ ...base, 'POST /parties/3/players': { status: 201, body: { id: 9, name: 'Dora', hasAccount: false, visible: true, playCount: 0 } } });
    renderApp('/parties/3/guests');
    const input = await screen.findByLabelText('Jméno nového hosta');
    expect(screen.getByRole('button', { name: 'Přidat' })).toBeDisabled();
    fireEvent.change(input, { target: { value: ' Dora ' } });
    fireEvent.keyDown(input, { key: 'Enter' });
    await waitFor(() => expect(sentBody(fetchMock, 'POST', '/parties/3/players')).toEqual({ name: 'Dora' }));
    await waitFor(() => expect(input).toHaveValue(''));
  });

  it('deletes an unused guest after confirming in a popup', async () => {
    const confirm = vi.spyOn(window, 'confirm');
    const fetchMock = mockApi({ ...base, 'DELETE /players/3': { status: 204, body: null } });
    renderApp('/parties/3/guests');
    fireEvent.click(await screen.findByRole('button', { name: 'Smazat hosta Cyril' }));
    const dialog = screen.getByRole('alertdialog', { name: 'Smazat hosta Cyril?' });
    expect(within(dialog).getByText(/nejde to vrátit zpět/)).toBeInTheDocument();
    expect(confirm).not.toHaveBeenCalled();
    expect(fetchMock.mock.calls.some(([, init]) => init?.method === 'DELETE')).toBe(false);
    fireEvent.click(within(dialog).getByRole('button', { name: 'Smazat' }));
    await waitFor(() => expect(fetchMock.mock.calls.some(([u, init]) => String(u) === '/api/players/3' && init?.method === 'DELETE')).toBe(true));
  });

  it('shows a toast when the server refuses (e.g. a taken name)', async () => {
    mockApi({ ...base, 'PATCH /players/2': { status: 422, body: { error: 'Hráč s tímto jménem už v partě existuje.' } } });
    renderApp('/parties/3/guests');
    fireEvent.change(await screen.findByLabelText('Jméno hosta Bob'), { target: { value: 'Cyril' } });
    fireEvent.click(screen.getByRole('button', { name: 'Uložit' }));
    expect(await screen.findByRole('alert')).toHaveTextContent('Hráč s tímto jménem už v partě existuje.');
  });

  it('says so when there are no guests', async () => {
    mockApi({ ...base, '/parties/3/players': { body: [PLAYERS[0]] } });
    renderApp('/parties/3/guests');
    expect(await screen.findByText('Zatím tu nejsou žádní hosté.')).toBeInTheDocument();
  });

  describe('turning a guest into a member', () => {
    it('links a guest with an existing member after confirming what happens', async () => {
      const fetchMock = mockApi({ ...base, 'POST /players/2/link-member': { body: { id: 5, name: 'Viki', hasAccount: true, visible: true, playCount: 4 } } });
      renderApp('/parties/3/guests');
      fireEvent.click(await screen.findByRole('button', { name: 'Propojit hosta Bob s členem' }));
      const dialog = screen.getByRole('dialog', { name: 'Host Bob' });
      fireEvent.click(within(dialog).getByRole('button', { name: /Propojit s členem/ }));
      fireEvent.click(screen.getByRole('option', { name: /Viki/ }));

      expect(screen.getByRole('dialog', { name: 'Propojit Bob s členem Viki?' })).toBeInTheDocument();
      expect(screen.getByText(/Všechny zápisy hosta Bob \(3 zápisy\) přejdou na člena Viki/)).toBeInTheDocument();
      expect(fetchMock.mock.calls.some(([, init]) => init?.method === 'POST' && String(init?.body).includes('userId'))).toBe(false);
      fireEvent.click(screen.getByRole('button', { name: 'Propojit' }));

      await waitFor(() => expect(sentBody(fetchMock, 'POST', '/players/2/link-member')).toEqual({ userId: 2 }));
      await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
    });

    it('shows the server error (e.g. a shared play) as a toast and keeps the popup', async () => {
      mockApi({ ...base, 'POST /players/2/link-member': { status: 422, body: { error: 'Host a člen hráli v 1 stejných zápisech, takže je nejde sloučit.' } } });
      renderApp('/parties/3/guests');
      fireEvent.click(await screen.findByRole('button', { name: 'Propojit hosta Bob s členem' }));
      fireEvent.click(screen.getByRole('button', { name: /Propojit s členem/ }));
      fireEvent.click(screen.getByRole('option', { name: /Viki/ }));
      fireEvent.click(screen.getByRole('button', { name: 'Propojit' }));
      expect(await screen.findByRole('alert')).toHaveTextContent('stejných zápisech');
      expect(screen.getByRole('dialog')).toBeInTheDocument();
    });

    it('creates an invitation for that guest and shows the link', async () => {
      const fetchMock = mockApi({ ...base, 'POST /parties/3/invitations': { status: 201, body: { token: 'tok42', expiresAt: '2030-01-01T00:00:00+00:00' } } });
      renderApp('/parties/3/guests');
      fireEvent.click(await screen.findByRole('button', { name: 'Propojit hosta Bob s členem' }));
      fireEvent.click(screen.getByRole('button', { name: /Pozvat jako člena/ }));

      const link = (await screen.findByLabelText('Odkaz pozvánky')) as HTMLInputElement;
      expect(link.value).toMatch(/\/join\/tok42$/);
      expect(screen.getByText(/host Bob se propojí s jeho účtem/)).toBeInTheDocument();
      expect(sentBody(fetchMock, 'POST', '/parties/3/invitations')).toEqual({ guestId: 2 });
    });

    it('closes without doing anything by the cross and Escape', async () => {
      const fetchMock = mockApi(base);
      renderApp('/parties/3/guests');
      const open = await screen.findByRole('button', { name: 'Propojit hosta Bob s členem' });
      open.focus();
      fireEvent.click(open);
      fireEvent.click(screen.getByRole('button', { name: 'Zavřít' }));
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
      expect(open).toHaveFocus();
      fireEvent.click(open);
      fireEvent.keyDown(screen.getByRole('dialog'), { key: 'Escape' });
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
      expect(fetchMock.mock.calls.some(([, init]) => init?.method === 'POST')).toBe(false);
    });
  });
});
