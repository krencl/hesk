import { QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { createQueryClient } from '../queryClient';
import { mockApi } from '../test/helpers';
import { Logo } from './Logo';

const renderLogo = () => render(<QueryClientProvider client={createQueryClient()}><Logo className="x" /></QueryClientProvider>);

describe('Logo', () => {
  it('je na dev prostředí zelené a přepne i favicony', async () => {
    document.head.innerHTML = '<link rel="shortcut icon" href="/static/favicon.png"><link rel="icon" href="/static/icon.png"><link rel="apple-touch-icon" href="/static/icon.png">';
    mockApi({ '/health': { body: { status: 'ok', env: 'dev' } } });
    renderLogo();
    await waitFor(() => expect(screen.getByAltText('HESK').getAttribute('src')).toMatch(/logo-dev\.svg$/));
    expect([...document.head.querySelectorAll('link')].map((l) => new URL(l.href).pathname)).toEqual(['/static/favicon-dev.png', '/static/icon-dev.png', '/static/icon-dev.png']);
  });

  it('je na produkci a při chybě API modré', async () => {
    mockApi({ '/health': { body: { status: 'ok', env: 'prod' } } });
    const { unmount } = renderLogo();
    await new Promise((r) => setTimeout(r, 50));
    expect(screen.getByAltText('HESK').getAttribute('src')).toMatch(/logo\.svg$/);
    unmount();
    mockApi({});
    renderLogo();
    await new Promise((r) => setTimeout(r, 50));
    expect(screen.getByAltText('HESK').getAttribute('src')).toMatch(/logo\.svg$/);
  });
});
