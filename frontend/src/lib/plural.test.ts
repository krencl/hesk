import { describe, expect, it } from 'vitest';
import { playsLabel } from './plural';

describe('playsLabel', () => {
  it('uses the Czech plural forms', () => {
    expect([0, 1, 2, 4, 5, 21].map(playsLabel)).toEqual(['0 zápisů', '1 zápis', '2 zápisy', '4 zápisy', '5 zápisů', '21 zápisů']);
  });
});
