import { afterEach, describe, expect, it } from 'vitest';
import { clearAfterLoginPath, peekAfterLoginPath, rememberAfterLoginPath } from './afterLogin';

afterEach(() => sessionStorage.clear());

describe('afterLogin', () => {
  it('remembers invitation paths until they are cleared', () => {
    rememberAfterLoginPath('/join/abc');
    expect(peekAfterLoginPath()).toBe('/join/abc');
    expect(peekAfterLoginPath()).toBe('/join/abc'); // čtení nic nemaže (StrictMode renderuje dvakrát)
    clearAfterLoginPath();
    expect(peekAfterLoginPath()).toBeNull();
  });

  it('ignores other paths', () => {
    rememberAfterLoginPath('/stats');
    expect(peekAfterLoginPath()).toBeNull();
  });
});
