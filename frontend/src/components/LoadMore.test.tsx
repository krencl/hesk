import { act, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { LoadMore } from './LoadMore';

class FakeObserver {
  static instances: FakeObserver[] = [];
  observed: Element[] = [];
  disconnected = false;
  constructor(public callback: IntersectionObserverCallback, public options?: IntersectionObserverInit) {
    FakeObserver.instances.push(this);
  }
  observe(el: Element) { this.observed.push(el); }
  disconnect() { this.disconnected = true; }
  trigger(isIntersecting: boolean) {
    act(() => this.callback([{ isIntersecting } as IntersectionObserverEntry], this as unknown as IntersectionObserver));
  }
  static get active() { return FakeObserver.instances.filter((i) => !i.disconnected); }
}

afterEach(() => { vi.unstubAllGlobals(); FakeObserver.instances = []; });
const withObserver = () => vi.stubGlobal('IntersectionObserver', FakeObserver);

describe('LoadMore', () => {
  it('loads the next page when the sentinel scrolls into view, without a button', () => {
    withObserver();
    const onLoad = vi.fn();
    render(<LoadMore hasMore loading={false} onLoad={onLoad} />);
    expect(screen.queryByRole('button')).not.toBeInTheDocument();
    expect(FakeObserver.active).toHaveLength(1);
    expect(FakeObserver.active[0].options?.rootMargin).toBe('400px 0px'); // dotahuje s předstihem

    FakeObserver.active[0].trigger(false);
    expect(onLoad).not.toHaveBeenCalled();
    FakeObserver.active[0].trigger(true);
    expect(onLoad).toHaveBeenCalledTimes(1);
  });

  it('observes again after a page was loaded, so a short page keeps filling the screen', () => {
    withObserver();
    const onLoad = vi.fn();
    const { rerender } = render(<LoadMore hasMore loading={false} onLoad={onLoad} />);
    rerender(<LoadMore hasMore loading onLoad={onLoad} />);
    expect(FakeObserver.active).toHaveLength(0); // během načítání se nic nespouští
    expect(screen.getByRole('status')).toHaveTextContent('Načítám…');
    rerender(<LoadMore hasMore loading={false} onLoad={onLoad} />);
    expect(FakeObserver.active).toHaveLength(1);
  });

  it('renders nothing when there is no further page', () => {
    withObserver();
    const { container } = render(<LoadMore hasMore={false} loading={false} onLoad={vi.fn()} />);
    expect(container).toBeEmptyDOMElement();
    expect(FakeObserver.active).toHaveLength(0);
  });

  it('stops auto-loading after a failure and offers a retry button', () => {
    withObserver();
    const onLoad = vi.fn();
    render(<LoadMore hasMore loading={false} failed onLoad={onLoad} />);
    expect(FakeObserver.active).toHaveLength(0);
    fireEvent.click(screen.getByRole('button', { name: 'Zkusit znovu' }));
    expect(onLoad).toHaveBeenCalledTimes(1);
  });

  it('falls back to a button where IntersectionObserver is missing', () => {
    vi.stubGlobal('IntersectionObserver', undefined);
    const onLoad = vi.fn();
    render(<LoadMore hasMore loading={false} onLoad={onLoad} label="Načíst další záznamy" />);
    fireEvent.click(screen.getByRole('button', { name: 'Načíst další záznamy' }));
    expect(onLoad).toHaveBeenCalledTimes(1);
  });
});
