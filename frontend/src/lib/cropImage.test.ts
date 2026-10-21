import { afterEach, describe, expect, it, vi } from 'vitest';
import { cropToBlob } from './cropImage';

const AREA = { x: 0, y: 0, width: 100, height: 100 };

/** jsdom canvas ani Image nedekóduje: nahradíme je zápisem, jakými typy se kódovalo; `supported` jsou typy, které „prohlížeč“ umí. */
function stubBrowser(supported: string[]) {
  const asked: string[] = [];
  vi.stubGlobal('Image', class {
    onload: () => void = () => {};
    set src(_: string) { queueMicrotask(() => this.onload()); }
  });
  vi.spyOn(HTMLCanvasElement.prototype, 'getContext').mockReturnValue({ fillRect: vi.fn(), drawImage: vi.fn() } as unknown as CanvasRenderingContext2D);
  vi.spyOn(HTMLCanvasElement.prototype, 'toBlob').mockImplementation((cb, type) => {
    asked.push(type ?? 'image/png');
    // prohlížeč bez podpory typu vrací PNG
    cb(new Blob(['x'], { type: supported.includes(type ?? '') ? type : 'image/png' }));
  });
  return asked;
}

afterEach(() => {
  vi.restoreAllMocks();
  vi.unstubAllGlobals();
  window.history.replaceState(null, '', '/');
});

describe('cropToBlob', () => {
  it('prefers WebP', async () => {
    const asked = stubBrowser(['image/webp', 'image/jpeg']);
    expect((await cropToBlob('blob:x', AREA)).type).toBe('image/webp');
    expect(asked).toEqual(['image/webp']);
  });

  it('falls back to JPEG when the browser cannot encode WebP (iPhone)', async () => {
    const asked = stubBrowser(['image/jpeg']);
    expect((await cropToBlob('blob:x', AREA)).type).toBe('image/jpeg');
    expect(asked).toEqual(['image/webp', 'image/jpeg']);
  });

  it('skips WebP with ?coverFormat=jpeg so the server conversion can be tested', async () => {
    window.history.replaceState(null, '', '/games/5/edit?coverFormat=jpeg');
    const asked = stubBrowser(['image/webp', 'image/jpeg']);
    expect((await cropToBlob('blob:x', AREA)).type).toBe('image/jpeg');
    expect(asked).toEqual(['image/jpeg']);
  });
});
