import { describe, expect, it } from 'vitest';
import { partyShortcut } from './partyShortcut';

describe('partyShortcut', () => {
  it('uses two letters of a single word', () => {
    expect(partyShortcut('Deskobraní')).toBe('De');
    expect(partyShortcut('ŠACH')).toBe('Ša');
  });
  it('uses initials of the first two words', () => {
    expect(partyShortcut('Pátek večer u Kuby')).toBe('PV');
  });
  it('handles short and blank names', () => {
    expect(partyShortcut('X')).toBe('X');
    expect(partyShortcut('   ')).toBe('?');
  });
});
