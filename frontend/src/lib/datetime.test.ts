import { describe, expect, it } from 'vitest';
import { formatDateShort, formatDateTimeShort } from './datetime';

describe('compact date formats', () => {
  const iso = new Date(2022, 1, 6, 9, 5).toISOString(); // lokální čas, aby test nezávisel na časové zóně

  it('formats date and time like the old prototype', () => {
    expect(formatDateTimeShort(iso)).toBe('6.2.2022 09:05');
    expect(formatDateShort(iso)).toBe('6.2.2022');
  });
});
