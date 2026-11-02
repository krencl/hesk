import { fireEvent, screen, waitFor } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { mockApi, ME, PARTY, renderApp, requestedUrls } from '../test/helpers';

const detail = { ...PARTY, members: [{ userId: 1, nickname: 'Anna', role: 'admin' }] };
const EXPORT = { meta: { format: 'hesk-party-export' }, players: [{ id: 1, name: 'Anna', visible: true }] };
const base = {
  '/me': { body: ME },
  '/parties': { body: [PARTY] },
  '/parties/3': { body: detail },
  '/parties/3/export*': { body: EXPORT },
};

let saved: Blob[];
let clicked: string[];

beforeEach(() => {
  saved = [];
  clicked = [];
  vi.stubGlobal('URL', Object.assign(URL, { createObjectURL: (blob: Blob) => (saved.push(blob), 'blob:export'), revokeObjectURL: () => undefined }));
  vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(function (this: HTMLAnchorElement) {
    clicked.push(this.download);
  });
});
afterEach(() => vi.restoreAllMocks());

const exportRequests = (fetchMock: ReturnType<typeof mockApi>) => requestedUrls(fetchMock).filter((u) => u.includes('/export'));
const select = async () => (await screen.findByLabelText('Rozsah exportu')) as HTMLSelectElement;

describe('party data (/parties/:id/data)', () => {
  it('is a tab of party management for admins only', async () => {
    mockApi({ ...base, '/parties/3': { body: { ...detail, role: 'writer' } }, '/parties': { body: [{ ...PARTY, role: 'writer' }] } });
    renderApp('/parties/3/guests');

    expect(await screen.findByRole('link', { name: 'Hosté' })).toBeInTheDocument();
    expect(screen.queryByRole('link', { name: 'Data' })).not.toBeInTheDocument();
  });

  it('sends a writer back to the member list', async () => {
    mockApi({ ...base, '/parties/3': { body: { ...detail, role: 'writer' } }, '/parties': { body: [{ ...PARTY, role: 'writer' }] } });
    renderApp('/parties/3/data');

    await waitFor(() => expect(screen.getByRole('link', { name: 'Členové' })).toHaveClass('active'));
    expect(screen.queryByRole('button', { name: 'Exportovat data' })).not.toBeInTheDocument();
  });

  it('exports everything by default and saves the response as a JSON file', async () => {
    const fetchMock = mockApi(base);
    renderApp('/parties/3/data');
    expect(await screen.findByRole('link', { name: 'Data' })).toHaveClass('active');
    expect(await select()).toHaveValue('all');

    fireEvent.click(screen.getByRole('button', { name: 'Exportovat data' }));

    await waitFor(() => expect(clicked).toHaveLength(1));
    expect(exportRequests(fetchMock)).toEqual(['/parties/3/export']);
    expect(clicked[0]).toMatch(/^hesk-export-\d{4}-\d{2}-\d{2}\.json$/);
    expect(JSON.parse(await saved[0].text())).toEqual(EXPORT);
  });

  it('exports sessions in a number range', async () => {
    const fetchMock = mockApi(base);
    renderApp('/parties/3/data');
    fireEvent.change(await select(), { target: { value: 'sessions' } });
    fireEvent.change(screen.getByLabelText('Od sezení'), { target: { value: '3' } });
    fireEvent.change(screen.getByLabelText('Do sezení (včetně)'), { target: { value: '5' } });

    fireEvent.click(screen.getByRole('button', { name: 'Exportovat data' }));

    await waitFor(() => expect(exportRequests(fetchMock)).toEqual(['/parties/3/export?fromSession=3&toSession=5']));
  });

  it('exports plays in a date range, the end day included', async () => {
    const fetchMock = mockApi(base);
    renderApp('/parties/3/data');
    fireEvent.change(await select(), { target: { value: 'dates' } });
    fireEvent.change(screen.getByLabelText('Od'), { target: { value: '2026-03-01' } });
    fireEvent.change(screen.getByLabelText('Do (včetně)'), { target: { value: '2026-03-31' } });

    fireEvent.click(screen.getByRole('button', { name: 'Exportovat data' }));

    await waitFor(() => expect(exportRequests(fetchMock)).toHaveLength(1));
    const query = new URLSearchParams(exportRequests(fetchMock)[0].split('?')[1]);
    expect(query.get('from')).toBe(new Date(2026, 2, 1).toISOString());
    expect(query.get('to')).toBe(new Date(2026, 3, 1).toISOString());
  });

  it('blocks a filtered export without a range or with a start after the end', async () => {
    mockApi(base);
    renderApp('/parties/3/data');
    fireEvent.change(await select(), { target: { value: 'sessions' } });
    const button = screen.getByRole('button', { name: 'Exportovat data' });
    expect(button).toBeDisabled();

    fireEvent.change(screen.getByLabelText('Od sezení'), { target: { value: '9' } });
    expect(button).toBeEnabled();
    fireEvent.change(screen.getByLabelText('Do sezení (včetně)'), { target: { value: '4' } });
    expect(button).toBeDisabled();
  });

  it('forgets the range when the scope changes', async () => {
    mockApi(base);
    renderApp('/parties/3/data');
    fireEvent.change(await select(), { target: { value: 'sessions' } });
    fireEvent.change(screen.getByLabelText('Od sezení'), { target: { value: '9' } });

    fireEvent.change(screen.getByLabelText('Rozsah exportu'), { target: { value: 'dates' } });

    expect(screen.getByLabelText('Od')).toHaveValue('');
    expect(screen.getByRole('button', { name: 'Exportovat data' })).toBeDisabled();
  });

  it('keeps the file unsaved and shows the error when the export fails', async () => {
    mockApi({ ...base, '/parties/3/export*': { status: 422, body: { error: 'Vyber buď rozsah dat, nebo rozsah sezení.' } } });
    renderApp('/parties/3/data');
    await select();

    fireEvent.click(screen.getByRole('button', { name: 'Exportovat data' }));

    expect(await screen.findByText('Vyber buď rozsah dat, nebo rozsah sezení.')).toBeInTheDocument();
    expect(clicked).toHaveLength(0);
  });
});
