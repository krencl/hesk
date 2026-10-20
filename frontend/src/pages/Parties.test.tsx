import { fireEvent, screen, waitFor, within } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { GAME, mockApi, ME, PARTY, renderApp } from '../test/helpers';

afterEach(() => { vi.unstubAllGlobals(); vi.restoreAllMocks(); sessionStorage.clear(); localStorage.clear(); });

const MEMBER_PARTY = { id: 4, name: 'Víkend', shortName: 'Ví', role: 'member' };
const detail = (party: { id: number; name: string; shortName: string; role: string }, members = [
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
    // aktivní parta je zvýrazněný řádek (ne tlačítko ani štítek), přepnout lze jen ostatní
    const active = screen.getByRole('link', { name: /Pátek/ }).closest('li')!;
    expect(active).toHaveAttribute('aria-current', 'true');
    expect(active).toHaveClass('selected');
    expect(screen.getByRole('link', { name: /Víkend/ }).closest('li')).not.toHaveClass('selected');
    expect(screen.getAllByRole('button', { name: 'Přepnout' })).toHaveLength(1);
    // role (admin/člen) se u part nepíše, je vidět v detailu party u členů
    expect(screen.queryByText(/^(admin|člen)$/)).not.toBeInTheDocument();
    // správa part už neobsahuje členy ani pozvánky
    expect(screen.queryByText('Členové')).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Pozvat hráče' })).not.toBeInTheDocument();
  });

  it('shows the "Vyber partu" heading of the menu in blue like the other menu headings', async () => {
    mockApi(base);
    renderApp('/parties');
    fireEvent.click(await screen.findByRole('button', { name: /^Menu – / }));
    fireEvent.click(screen.getByRole('button', { name: 'Přepnout' }));
    expect(screen.getByRole('heading', { name: 'Vyber partu' })).toHaveClass('primary');
  });

  it('switches the active party', async () => {
    mockApi(base);
    renderApp('/parties');
    fireEvent.click(await screen.findByRole('button', { name: 'Přepnout' }));
    await waitFor(() => expect(localStorage.length).toBeGreaterThan(0));
    await waitFor(() => expect(screen.getByRole('link', { name: /Víkend/ }).closest('li')).toHaveAttribute('aria-current', 'true'));
    expect(screen.getByRole('link', { name: /Pátek/ }).closest('li')).not.toHaveAttribute('aria-current');
  });

  it('creates a party and opens its detail', async () => {
    const created = { id: 9, name: 'Nová', shortName: 'No', role: 'admin' };
    const fetchMock = mockApi({
      ...base,
      'POST /parties': { status: 201, body: detail(created, [{ userId: 1, nickname: 'Anna', role: 'admin' }]) },
      '/parties/9': { body: detail(created, [{ userId: 1, nickname: 'Anna', role: 'admin' }]) },
      '/parties/9/games?sort=activity&limit=21&offset=0': { body: [] },
    });
    renderApp('/parties');
    fireEvent.change(await screen.findByLabelText('Název nové party'), { target: { value: ' Nová ' } });
    fireEvent.click(screen.getByRole('button', { name: 'Vytvořit' }));

    expect(await screen.findByRole('heading', { name: 'Parta Nová' })).toBeInTheDocument();
    const post = fetchMock.mock.calls.find(([u, init]) => String(u) === '/api/parties' && init?.method === 'POST')!;
    expect(JSON.parse(String(post[1]?.body))).toEqual({ name: 'Nová' });
    expect(screen.getByRole('button', { name: 'Pozvat hráče' })).toBeInTheDocument();
  });
});

describe('party detail (/parties/:id)', () => {
  it('lets an admin manage members and invite players', async () => {
    mockApi({ ...base, '/parties/3': { body: detail(PARTY) }, '/parties/3/invitations': { status: 201, body: { token: 'abc', expiresAt: '2030-01-01T00:00:00+00:00' } } });
    renderApp('/parties/3');
    expect(await screen.findByRole('heading', { name: 'Parta Pátek' })).toBeInTheDocument();
    expect(screen.queryByText(/Jsi (admin|člen)/)).not.toBeInTheDocument(); // role je vidět ze seznamu členů
    expect(screen.getByText('Petr')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Udělat adminem' })).toBeInTheDocument();
    expect(screen.getAllByRole('button', { name: 'Odebrat' })).toHaveLength(1); // sebe odebrat nelze

    fireEvent.click(screen.getByRole('button', { name: 'Pozvat hráče' }));
    const link = (await screen.findByLabelText('Odkaz pozvánky')) as HTMLInputElement;
    expect(link.value).toMatch(/\/join\/abc$/);
    expect(screen.getByRole('link', { name: 'Správa part' })).toHaveAttribute('href', '/parties');
  });

  it('lets an admin edit the name and the short name on a separate page and returns to the detail', async () => {
    const updated = { ...PARTY, name: 'Pátek večer', shortName: 'PTK' };
    const fetchMock = mockApi({
      ...base,
      '/parties/3': { body: detail(PARTY) },
      'PATCH /parties/3': { body: detail(updated) },
      '/parties/3/games?sort=activity&limit=21&offset=0': { body: [GAME] },
    });
    renderApp('/parties/3');
    fireEvent.click(await screen.findByRole('link', { name: 'Nastavení' }));
    expect(await screen.findByLabelText('Název')).toHaveValue('Pátek');
    expect(screen.getByLabelText(/Zkratka/)).toHaveValue('Pá'); // zkratka vygenerovaná při založení
    fireEvent.change(screen.getByLabelText('Název'), { target: { value: ' Pátek večer ' } });
    fireEvent.change(screen.getByLabelText(/Zkratka/), { target: { value: ' PTK ' } });
    fireEvent.click(screen.getByRole('button', { name: 'Uložit' }));

    await waitFor(() => expect(fetchMock.mock.calls.some(([u, init]) => String(u) === '/api/parties/3' && init?.method === 'PATCH')).toBe(true));
    const patch = fetchMock.mock.calls.find(([, init]) => init?.method === 'PATCH')!;
    expect(JSON.parse(String(patch[1]?.body))).toEqual({ name: 'Pátek večer', shortName: 'PTK' });
    expect(await screen.findByRole('link', { name: 'Pozvat hráče' }).catch(() => screen.findByRole('button', { name: 'Pozvat hráče' }))).toBeInTheDocument(); // zpět na detail
    expect(screen.queryByLabelText('Název')).not.toBeInTheDocument();
  });

  it('opens the edit page directly by its URL', async () => {
    mockApi({ ...base, '/parties/3': { body: detail(PARTY) } });
    renderApp('/parties/3/edit');
    expect(await screen.findByRole('heading', { name: 'Parta Pátek' })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Nastavení' })).toHaveClass('active');
    expect(screen.getByRole('link', { name: 'Zrušit' })).toHaveAttribute('href', '/parties/3');
  });

  it('offers the sections as tabs with their own addresses; settings only for an admin', async () => {
    mockApi({ ...base, '/parties/3': { body: detail(PARTY) }, '/parties/4': { body: detail(MEMBER_PARTY) } });
    const { unmount } = renderApp('/parties/3');
    await screen.findByRole('heading', { name: 'Parta Pátek' });
    const tabs = within(screen.getByRole('navigation', { name: 'Správa party' }));
    expect(tabs.getAllByRole('link').map((a) => [a.textContent, a.getAttribute('href')])).toEqual([
      ['Členové', '/parties/3'], ['Hosté', '/parties/3/guests'], ['Nastavení', '/parties/3/edit'],
    ]);
    expect(tabs.getByRole('link', { name: 'Členové' })).toHaveClass('active');
    expect(screen.queryByRole('link', { name: 'Upravit partu' })).not.toBeInTheDocument();
    unmount();

    renderApp('/parties/4');
    await screen.findByRole('heading', { name: 'Parta Víkend' });
    expect(within(screen.getByRole('navigation', { name: 'Správa party' })).getAllByRole('link').map((a) => a.textContent)).toEqual(['Členové', 'Hosté']);
  });

  it('sends a plain member from the edit page back to the detail', async () => {
    mockApi({ ...base, '/parties/4': { body: detail(MEMBER_PARTY) } });
    renderApp('/parties/4/edit');
    expect(await screen.findByRole('heading', { name: 'Parta Víkend' })).toBeInTheDocument();
    expect(screen.queryByLabelText('Název')).not.toBeInTheDocument();
  });

  it('shows the short name of the active party in the header button', async () => {
    mockApi({ ...base, '/parties': { body: [{ ...PARTY, shortName: 'PTK' }, MEMBER_PARTY] }, '/parties/3': { body: detail(PARTY) } });
    renderApp('/parties/3');
    expect(await screen.findByRole('button', { name: /Menu – Pátek/ })).toHaveTextContent('PTK');
  });

  it('requires a short name (at least one character)', async () => {
    const fetchMock = mockApi({ ...base, '/parties/3': { body: detail(PARTY) }, 'PATCH /parties/3': { body: detail(PARTY) } });
    renderApp('/parties/3/edit');
    fireEvent.change(await screen.findByLabelText(/Zkratka/), { target: { value: '  ' } });
    expect(screen.getByLabelText(/Zkratka/)).toBeRequired();
    expect(screen.getByRole('button', { name: 'Uložit' })).toBeDisabled();
    fireEvent.change(screen.getByLabelText(/Zkratka/), { target: { value: 'X' } });
    expect(screen.getByRole('button', { name: 'Uložit' })).toBeEnabled();
    expect(fetchMock.mock.calls.some(([, init]) => init?.method === 'PATCH')).toBe(false);
  });

  it('gives a plain member no admin actions but offers leaving', async () => {
    mockApi({ ...base, '/parties/4': { body: detail(MEMBER_PARTY, [{ userId: 1, nickname: 'Anna', role: 'member' }, { userId: 2, nickname: 'Petr', role: 'admin' }]) } });
    renderApp('/parties/4');
    expect(await screen.findByRole('heading', { name: 'Parta Víkend' })).toBeInTheDocument();
    expect(screen.queryByText(/Jsi (admin|člen)/)).not.toBeInTheDocument();
    expect(screen.queryByRole('link', { name: 'Upravit partu' })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Pozvat hráče' })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Udělat adminem' })).not.toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Opustit' })).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /Přepnout na tuto partu/ })).not.toBeInTheDocument();
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
    expect(screen.getByRole('link', { name: 'Spravovat' })).toHaveAttribute('href', '/parties/3');
    expect(screen.queryByRole('link', { name: 'Zobrazit detail' })).not.toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Spravovat party' })).toHaveAttribute('href', '/parties');
    // v sekci Parta je „Spravovat“ až za „Statistiky“
    const stats = screen.getByRole('link', { name: 'Statistiky' });
    expect(stats.compareDocumentPosition(screen.getByRole('link', { name: 'Spravovat' })) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
  });
});
