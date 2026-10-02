import { afterEach, describe, expect, it } from 'vitest';
import { consumeAfterLoginPath, rememberAfterLoginPath } from './afterLogin';

afterEach(() => sessionStorage.clear());

describe('afterLogin', () => {
  it('remembers invitation paths once', () => {
    rememberAfterLoginPath('/join/abc');
    expect(consumeAfterLoginPath()).toBe('/join/abc');
    expect(consumeAfterLoginPath()).toBeNull();
  });

  it('ignores other paths', () => {
    rememberAfterLoginPath('/stats');
    expect(consumeAfterLoginPath()).toBeNull();
  });
});
