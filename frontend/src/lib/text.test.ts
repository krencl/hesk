import { describe, expect, it } from 'vitest';
import { normalize } from './text';

describe('normalize', () => {
  it('drops diacritics, case and surrounding spaces', () => {
    expect(normalize('  Špalíček ŘÍŠE ')).toBe('spalicek rise');
  });
});
