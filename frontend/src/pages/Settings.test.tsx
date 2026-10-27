import { fireEvent, screen, waitFor } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { GAME, mockApi, ME, PARTY, renderApp, sentBody } from '../test/helpers';

afterEach(() => vi.restoreAllMocks());

const detail = (partyNickname: string | null) => ({ ...PARTY, members: [{ userId: 1, nickname: partyNickname ?? 'Anna', partyNickname, role: 'admin' }] });
const routes = (partyNickname: string | null = null) => ({
  '/me': { body: ME },
  '/parties': { body: [PARTY] },
  '/parties/3': { body: detail(partyNickname) },
  '/parties/3/games?sort=activity&limit=21&offset=0': { body: [GAME] },
});

describe('settings: nicknames', () => {
  it('describes the default nickname and offers a nickname in the active party with the default as placeholder', async () => {
    mockApi(routes());
    renderApp('/settings');
    expect(await screen.findByText('Defaultní nick, použije se pro nové party')).toBeInTheDocument();
    const field = await screen.findByLabelText(/Nick v partě Pátek/);
    expect(field).toHaveValue('');
    expect(field).toHaveAttribute('placeholder', 'Anna');
    expect(screen.getByText('Pokud je pole prázdné, použije se defaultní nick.')).toBeInTheDocument();
  });

  it('shows the current nickname in the party and saves a changed one', async () => {
    const fetchMock = mockApi({ ...routes('Aniččin'), '/parties/3/me': { body: detail('Anička') } });
    renderApp('/settings');
    const field = await screen.findByLabelText(/Nick v partě Pátek/);
    expect(field).toHaveValue('Aniččin');
    fireEvent.change(field, { target: { value: ' Anička ' } });
    fireEvent.click(screen.getAllByRole('button', { name: 'Uložit' })[1]);
    await waitFor(() => expect(sentBody(fetchMock, 'PATCH', '/parties/3/me')).toEqual({ nickname: 'Anička' }));
  });

  it('sends null when the nickname in the party is cleared (back to the default nickname)', async () => {
    const fetchMock = mockApi({ ...routes('Aniččin'), '/parties/3/me': { body: detail(null) } });
    renderApp('/settings');
    fireEvent.change(await screen.findByLabelText(/Nick v partě Pátek/), { target: { value: '' } });
    fireEvent.click(screen.getAllByRole('button', { name: 'Uložit' })[1]);
    await waitFor(() => expect(sentBody(fetchMock, 'PATCH', '/parties/3/me')).toEqual({ nickname: null }));
  });
});
