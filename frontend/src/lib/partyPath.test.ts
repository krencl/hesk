import { describe, expect, it } from 'vitest';
import { pathForSwitchedParty } from './partyPath';

describe('pathForSwitchedParty', () => {
  it('moves the party detail and edit pages under the new party', () => {
    expect(pathForSwitchedParty('/parties/6', 1)).toBe('/parties/1');
    expect(pathForSwitchedParty('/parties/6/', 1)).toBe('/parties/1');
    expect(pathForSwitchedParty('/parties/6/edit', 1)).toBe('/parties/1/edit');
    expect(pathForSwitchedParty('/parties/6/guests', 1)).toBe('/parties/1/guests');
    expect(pathForSwitchedParty('/parties/6/stats', 1)).toBe('/parties/1/stats');
  });

  it('leaves other pages alone', () => {
    expect(pathForSwitchedParty('/parties', 1)).toBeNull();
    expect(pathForSwitchedParty('/games/6', 1)).toBeNull();
    expect(pathForSwitchedParty('/parties/6/members', 1)).toBeNull();
  });
});
