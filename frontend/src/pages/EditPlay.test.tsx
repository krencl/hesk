import { fireEvent, screen, waitFor } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { toLocalInput } from '../lib/datetime';
import { GAME, mockApi, ME, PARTY, PLAY, renderApp, sentBody } from '../test/helpers';

const PLAYERS = [{ id: 1, name: 'Anna', hasAccount: true, visible: true }, { id: 2, name: 'Petr', hasAccount: false, visible: true }, { id: 3, name: 'Eva', hasAccount: false, visible: true }];
// Anna je hlavní hráč, Petr (vybraný) a Eva (nevybraná) jsou hosté
const PLAY_FULL = { ...PLAY, addons: [{ id: 5, name: 'Řeka' }], tags: ['rodinná', 'liga'], notes: 'Skvělá hra', endedAt: '2022-02-06T21:36:00+00:00' };
const routes = {
  '/me': { body: ME },
  '/parties': { body: [PARTY] },
  '/plays/9': { body: PLAY_FULL },
  '/parties/3/games/1': { body: { ...GAME, addons: [{ id: 5, name: 'Řeka' }, { id: 6, name: 'Opat' }] } },
  '/parties/3/players': { body: PLAYERS },
  '/parties/3/tags': { body: ['liga', 'rodinná', 'víkend'] },
  '/parties/3/plays?limit=11&offset=0&gameId=1': { body: [PLAY_FULL] },
};

describe('edit play (/plays/:id/edit)', () => {
  it('prefills the form from the play', async () => {
    mockApi(routes);
    renderApp('/plays/9/edit');
    expect(await screen.findByRole('heading', { name: 'Upravit zápis' })).toBeInTheDocument();
    expect(screen.getByText('Carcassonne')).toBeInTheDocument();
    expect(screen.getByLabelText('Anna')).toBeChecked();
    expect(screen.getByLabelText('Petr')).toBeChecked();
    expect(screen.queryByLabelText('Eva')).not.toBeInTheDocument(); // nevybraný host je schovaný
    expect(screen.getByRole('button', { name: 'Začíná Anna' })).toHaveAttribute('aria-pressed', 'true');
    expect(screen.getByRole('button', { name: 'Vítěz Anna' })).toHaveAttribute('aria-pressed', 'true');
    expect(screen.getByRole('button', { name: 'Vítěz Petr' })).toHaveAttribute('aria-pressed', 'false');
    expect(screen.getByLabelText('Skóre Anna')).toHaveValue('90');
    expect(screen.getByLabelText('Skóre Petr')).toHaveValue('75');
    expect(screen.getByLabelText('Řeka')).toBeChecked();
    expect(screen.getByLabelText('Opat')).not.toBeChecked();
    expect(screen.getByLabelText('Číslo sezení')).toHaveValue(69);
    expect(screen.getByRole('button', { name: 'Odebrat tag rodinná' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Odebrat tag liga' })).toBeInTheDocument();
    expect(screen.getByLabelText('Poznámka')).toHaveValue('Skvělá hra');
    expect(screen.getByLabelText('Začátek')).toHaveValue(toLocalInput(new Date(PLAY_FULL.startedAt)));
    expect(screen.getByLabelText('Konec')).toHaveValue(toLocalInput(new Date(PLAY_FULL.endedAt)));
    expect(screen.getByRole('link', { name: 'Zrušit' })).toHaveAttribute('href', '/games/1');
  });

  it('keeps a hidden guest who already has a result in the edited play, and offers no other hidden guest', async () => {
    const players = [PLAYERS[0], { ...PLAYERS[1], visible: false }, { ...PLAYERS[2], visible: false }];
    mockApi({ ...routes, '/parties/3/players': { body: players } });
    renderApp('/plays/9/edit');
    expect(await screen.findByLabelText('Petr')).toBeChecked(); // skrytý, ale v zápisu hraje → zůstává
    expect(screen.getByLabelText('Skóre Petr')).toHaveValue('75');
    fireEvent.click(screen.getByRole('button', { name: 'Přidat hosty' }));
    expect(screen.queryByLabelText('Eva')).not.toBeInTheDocument(); // skrytý a v zápisu nehraje → nenabízí se
  });

  it('saves the changes with PUT and returns to the game', async () => {
    const fetchMock = mockApi({ ...routes, 'PUT /plays/9': { body: PLAY_FULL } });
    renderApp('/plays/9/edit');
    fireEvent.change(await screen.findByLabelText('Skóre Petr'), { target: { value: '100' } });
    fireEvent.click(screen.getByRole('button', { name: 'Přidat hosty' }));
    fireEvent.click(screen.getByLabelText('Eva'));
    fireEvent.change(screen.getByLabelText('Skóre Eva'), { target: { value: '10' } });
    fireEvent.click(screen.getByLabelText('Řeka'));
    fireEvent.click(screen.getByRole('button', { name: 'Uložit změny' }));

    await waitFor(() => expect(sentBody(fetchMock, 'PUT', '/plays/9')).toBeDefined());
    const body = sentBody(fetchMock, 'PUT', '/plays/9');
    expect(body).toMatchObject({ gameId: 1, addonIds: [], tags: ['rodinná', 'liga'], notes: 'Skvělá hra' });
    expect(body.players).toEqual([
      { playerId: 1, score: 90, starting: true },
      { playerId: 2, score: 100, starting: false },
      { playerId: 3, score: 10, starting: false },
    ]);
    expect(await screen.findByRole('link', { name: 'Nový zápis' })).toHaveAttribute('href', '/games/1/new-play');
  });

  it('validates before saving', async () => {
    const fetchMock = mockApi(routes);
    renderApp('/plays/9/edit');
    fireEvent.change(await screen.findByLabelText('Skóre Petr'), { target: { value: '' } });
    fireEvent.click(screen.getByRole('button', { name: 'Uložit změny' }));
    expect(await screen.findByRole('alert')).toHaveTextContent('Zadej skóre všem hráčům.');
    expect(fetchMock.mock.calls.some(([, init]) => init?.method === 'PUT')).toBe(false);
  });

  it('has the delete button below the form actions and asks for confirmation', async () => {
    const fetchMock = mockApi({ ...routes, 'DELETE /plays/9': { status: 204, body: null } });
    renderApp('/plays/9/edit');
    const save = await screen.findByRole('button', { name: 'Uložit změny' });
    const del = screen.getByRole('button', { name: 'Smazat zápis' });
    expect(save.compareDocumentPosition(del) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();

    fireEvent.click(del);
    expect(fetchMock.mock.calls.some(([, init]) => init?.method === 'DELETE')).toBe(false);
    fireEvent.click(screen.getByRole('button', { name: 'Potvrď smazání' }));
    await waitFor(() => expect(fetchMock.mock.calls.some(([u, init]) => String(u) === '/api/plays/9' && init?.method === 'DELETE')).toBe(true));
    expect(await screen.findByRole('link', { name: 'Nový zápis' })).toBeInTheDocument();
  });

  it('shows a friendly message for an unknown play', async () => {
    mockApi({ ...routes, '/plays/9': { status: 404, body: { error: 'nf' } } });
    renderApp('/plays/9/edit');
    expect(await screen.findByText(/Tenhle zápis neexistuje/)).toBeInTheDocument();
  });
});
