import { fireEvent, screen, waitFor } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { GAME, mockApi, ME, PARTY, renderApp } from '../test/helpers';

afterEach(() => { vi.unstubAllGlobals(); vi.restoreAllMocks(); sessionStorage.clear(); localStorage.clear(); });

const detail = { ...PARTY, members: [{ userId: 1, nickname: 'Anna', role: 'admin' }] };
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

    await waitFor(() => expect(fetchMock.mock.calls.some(([u, init]) => String(u) === '/api/players/2' && init?.method === 'PATCH')).toBe(true));
    expect(JSON.parse(String(fetchMock.mock.calls.find(([, init]) => init?.method === 'PATCH')![1]?.body))).toEqual({ name: 'Bobík' });
    expect(fetchMock.mock.calls.filter(([, init]) => init?.method === 'PATCH')).toHaveLength(1);
  });

  it('toggles whether a guest is offered in plays and saves just that change', async () => {
    const fetchMock = mockApi({ ...base, 'PATCH /players/2': { body: { id: 2, name: 'Bob', hasAccount: false, visible: false } }, 'PATCH /players/3': { body: { id: 3, name: 'Cyril', hasAccount: false, visible: true } } });
    renderApp('/parties/3/guests');
    const bob = await screen.findByRole('button', { name: 'Zobrazovat hosta Bob' });
    const cyril = screen.getByRole('button', { name: 'Zobrazovat hosta Cyril' });
    expect(bob).toHaveAttribute('aria-pressed', 'true');
    expect(cyril).toHaveAttribute('aria-pressed', 'false'); // skrytý host
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
    const bodies = Object.fromEntries(fetchMock.mock.calls.filter(([, init]) => init?.method === 'PATCH').map(([u, init]) => [String(u), JSON.parse(String(init?.body))]));
    expect(bodies).toEqual({ '/api/players/2': { visible: false }, '/api/players/3': { visible: true } });
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
    await waitFor(() => expect(fetchMock.mock.calls.some(([u, init]) => String(u) === '/api/parties/3/players' && init?.method === 'POST')).toBe(true));
    expect(JSON.parse(String(fetchMock.mock.calls.find(([, init]) => init?.method === 'POST')![1]?.body))).toEqual({ name: 'Dora' });
    await waitFor(() => expect(input).toHaveValue(''));
  });

  it('deletes an unused guest after confirmation', async () => {
    const confirm = vi.spyOn(window, 'confirm').mockReturnValue(true);
    const fetchMock = mockApi({ ...base, 'DELETE /players/3': { status: 204, body: null } });
    renderApp('/parties/3/guests');
    fireEvent.click(await screen.findByRole('button', { name: 'Smazat hosta Cyril' }));
    expect(confirm).toHaveBeenCalled();
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
});
