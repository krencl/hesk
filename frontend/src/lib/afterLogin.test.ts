import { describe, expect, it } from 'vitest';
import { clearAfterLoginPath, peekAfterLoginPath, peekInvitationToken, rememberAfterLoginPath } from './afterLogin';

describe('afterLogin', () => {
  it('remembers invitation paths until they are cleared', () => {
    rememberAfterLoginPath('/join/abc');
    expect(peekAfterLoginPath()).toBe('/join/abc');
    expect(peekAfterLoginPath()).toBe('/join/abc'); // čtení nic nemaže (StrictMode renderuje dvakrát)
    clearAfterLoginPath();
    expect(peekAfterLoginPath()).toBeNull();
  });

  it('extracts the invitation token from the remembered path', () => {
    expect(peekInvitationToken()).toBeNull();
    rememberAfterLoginPath('/join/abc123');
    expect(peekInvitationToken()).toBe('abc123');
  });

  it('ignores other paths', () => {
    rememberAfterLoginPath('/stats');
    expect(peekAfterLoginPath()).toBeNull();
  });
});
