import { describe, expect, it } from 'vitest';
import { deriveShortcut, partyShortcut } from './partyShortcut';

describe('deriveShortcut', () => {
  it('uses two letters of a single word', () => {
    expect(deriveShortcut('Deskobraní')).toBe('De');
    expect(deriveShortcut('ŠACH')).toBe('Ša');
  });
  it('uses initials of the first two words', () => {
    expect(deriveShortcut('Pátek večer u Kuby')).toBe('PV');
  });
  it('handles short and blank names', () => {
    expect(deriveShortcut('X')).toBe('X');
    expect(deriveShortcut('   ')).toBe('?');
  });
});

describe('partyShortcut', () => {
  it('prefers the explicit short name and falls back to the derived one', () => {
    expect(partyShortcut({ name: 'Pátek večer', shortName: 'PTK' })).toBe('PTK');
    expect(partyShortcut({ name: 'Pátek večer', shortName: null })).toBe('PV');
    expect(partyShortcut({ name: 'Pátek večer', shortName: '  ' })).toBe('PV');
    expect(partyShortcut({ name: 'Pátek večer' })).toBe('PV');
  });
});
