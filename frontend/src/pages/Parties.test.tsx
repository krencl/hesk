import { fireEvent, screen, waitFor } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { GAME, mockApi, ME, PARTY, renderApp } from '../test/helpers';

afterEach(() => { vi.unstubAllGlobals(); vi.restoreAllMocks(); sessionStorage.clear(); localStorage.clear(); });

const MEMBER_PARTY = { id: 4, name: 'Víkend', role: 'member' };
const detail = (party: { id: number; name: string; role: string }, members = [
  { userId: 1, nickname: 'Anna', role: party.role },
  { userId: 2, nickname: 'Petr', role: 'member' },
]) => ({ ...party, members });
const base = {
  '/me': { body: ME },
  '/parties': { body: [PARTY, MEMBER_PARTY] },
  '/parties/3/games?sort=activity&limit=21&offset=0': { body: [GAME] },
};

describe('party management (/parties)', () => {
  it('lists the parties, marks the active one and links to the detail', async () => {
    mockApi(base);
    renderApp('/parties');
    expect(await screen.findByRole('heading', { name: 'Správa part' })).toBeInTheDocument();
    expect(await screen.findByRole('link', { name: /Pátek/ })).toHaveAttribute('href', '/parties/3');
    expect(screen.getByRole('link', { name: /Víkend/ })).toHaveAttribute('href', '/parties/4');
    expect(screen.getByText('Aktivní')).toBeInTheDocument();
    expect(screen.getAllByRole('button', { name: 'Přepnout' })).toHaveLength(1);
    // správa part už neobsahuje členy ani pozvánky
    expect(screen.queryByText('Členové')).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Pozvat hráče' })).not.toBeInTheDocument();
  });

  it('switches the active party', async () => {
    mockApi(base);
    renderApp('/parties');
    fireEvent.click(await screen.findByRole('button', { name: 'Přepnout' }));
    await waitFor(() => expect(localStorage.length).toBeGreaterThan(0));
    expect(await screen.findByText('Aktivní')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: /Víkend/ }).closest('li')).toHaveTextContent('Aktivní');
  });

  it('creates a party and opens its detail', async () => {
    const created = { id: 9, name: 'Nová', role: 'admin' };
    const fetchMock = mockApi({
      ...base,
      'POST /parties': { status: 201, body: detail(created, [{ userId: 1, nickname: 'Anna', role: 'admin' }]) },
      '/parties/9': { body: detail(created, [{ userId: 1, nickname: 'Anna', role: 'admin' }]) },
      '/parties/9/games?sort=activity&limit=21&offset=0': { body: [] },
    });
    renderApp('/parties');
    fireEvent.change(await screen.findByLabelText('Název nové party'), { target: { value: ' Nová ' } });
    fireEvent.click(screen.getByRole('button', { name: 'Vytvořit' }));

    expect(await screen.findByRole('heading', { name: 'Nová' })).toBeInTheDocument();
    const post = fetchMock.mock.calls.find(([u, init]) => String(u) === '/api/parties' && init?.method === 'POST')!;
    expect(JSON.parse(String(post[1]?.body))).toEqual({ name: 'Nová' });
    expect(screen.getByRole('button', { name: 'Pozvat hráče' })).toBeInTheDocument();
  });
});

describe('party detail (/parties/:id)', () => {
  it('lets an admin manage members and invite players', async () => {
    mockApi({ ...base, '/parties/3': { body: detail(PARTY) }, '/parties/3/invitations': { status: 201, body: { token: 'abc', expiresAt: '2030-01-01T00:00:00+00:00' } } });
    renderApp('/parties/3');
    expect(await screen.findByRole('heading', { name: 'Pátek' })).toBeInTheDocument();
    expect(screen.getByText('Jsi admin této party.')).toBeInTheDocument();
    expect(screen.getByText('Petr')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Udělat adminem' })).toBeInTheDocument();
    expect(screen.getAllByRole('button', { name: 'Odebrat' })).toHaveLength(1); // sebe odebrat nelze

    fireEvent.click(screen.getByRole('button', { name: 'Pozvat hráče' }));
    const link = (await screen.findByLabelText('Odkaz pozvánky')) as HTMLInputElement;
    expect(link.value).toMatch(/\/join\/abc$/);
    expect(screen.getByRole('link', { name: 'Správa part' })).toHaveAttribute('href', '/parties');
  });

  it('gives a plain member no admin actions but offers leaving, and can switch to a non-active party', async () => {
    mockApi({ ...base, '/parties/4': { body: detail(MEMBER_PARTY, [{ userId: 1, nickname: 'Anna', role: 'member' }, { userId: 2, nickname: 'Petr', role: 'admin' }]) } });
    renderApp('/parties/4');
    expect(await screen.findByRole('heading', { name: 'Víkend' })).toBeInTheDocument();
    expect(screen.getByText('Jsi člen této party.')).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Pozvat hráče' })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Udělat adminem' })).not.toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Opustit' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Přepnout na tuto partu' })).toBeInTheDocument();
  });

  it('asks for confirmation before leaving and returns to the management page afterwards', async () => {
    const confirm = vi.spyOn(window, 'confirm').mockReturnValue(true);
    const fetchMock = mockApi({
      ...base,
      '/parties/4': { body: detail(MEMBER_PARTY, [{ userId: 1, nickname: 'Anna', role: 'member' }]) },
      '/parties/4/members/1': { status: 204, body: null },
    });
    renderApp('/parties/4');
    fireEvent.click(await screen.findByRole('button', { name: 'Opustit' }));
    expect(confirm).toHaveBeenCalledWith('Opustit partu?');
    await waitFor(() => expect(fetchMock.mock.calls.some(([u, init]) => String(u) === '/api/parties/4/members/1' && init?.method === 'DELETE')).toBe(true));
    expect(await screen.findByRole('heading', { name: 'Správa part' })).toBeInTheDocument();
  });

  it('shows a friendly message for an unknown party', async () => {
    mockApi({ ...base, '/parties/99': { status: 404, body: { error: 'nf' } } });
    renderApp('/parties/99');
    expect(await screen.findByText(/neexistuje nebo v ní nejsi/)).toBeInTheDocument();
  });

  it('is reachable from the menu for the active party', async () => {
    mockApi(base);
    renderApp('/games');
    await screen.findByRole('searchbox', { name: 'Hledat hru' });
    fireEvent.click(await screen.findByRole('button', { name: /Menu – Pátek/ }));
    expect(screen.getByRole('link', { name: 'Zobrazit detail' })).toHaveAttribute('href', '/parties/3');
    expect(screen.getByRole('link', { name: 'Spravovat party' })).toHaveAttribute('href', '/parties');
  });
});
