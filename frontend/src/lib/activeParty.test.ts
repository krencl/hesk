import { describe, expect, it } from 'vitest';
import { loadActivePartyId, resolveActiveParty, saveActivePartyId } from './activeParty';

const parties = [
  { id: 1, name: 'A', role: 'admin' as const },
  { id: 2, name: 'B', role: 'member' as const },
];

describe('active party storage', () => {
  it('round-trips the id', () => {
    expect(loadActivePartyId()).toBeNull();
    saveActivePartyId(2);
    expect(loadActivePartyId()).toBe(2);
    saveActivePartyId(null);
    expect(loadActivePartyId()).toBeNull();
  });

  it('ignores garbage values', () => {
    localStorage.setItem('hesk.activePartyId', 'abc');
    expect(loadActivePartyId()).toBeNull();
  });
});

describe('resolveActiveParty', () => {
  it('prefers the stored party', () => {
    expect(resolveActiveParty(parties, 2)?.id).toBe(2);
  });

  it('falls back to the first party when stored one is gone', () => {
    expect(resolveActiveParty(parties, 99)?.id).toBe(1);
  });

  it('returns null without parties', () => {
    expect(resolveActiveParty([], 1)).toBeNull();
  });
});
