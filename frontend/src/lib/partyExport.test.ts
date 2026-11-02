import { describe, expect, it } from 'vitest';
import { exportParams, isExportRangeValid } from './partyExport';

describe('exportParams', () => {
  it('sends nothing for the whole export, whatever is left in the fields', () => {
    expect(exportParams({ scope: 'all', from: '5', to: '2026-01-01' })).toEqual({});
  });

  it('sends session numbers as they are and drops empty ends', () => {
    expect(exportParams({ scope: 'sessions', from: '3', to: '' })).toEqual({ fromSession: '3', toSession: undefined });
  });

  it('sends dates as local midnight instants with the end day exclusive', () => {
    const params = exportParams({ scope: 'dates', from: '2026-03-01', to: '2026-03-31' });

    expect(params.from).toBe(new Date(2026, 2, 1).toISOString());
    expect(params.to).toBe(new Date(2026, 3, 1).toISOString()); // 1 April 00:00 = the end of 31 March
    expect(params.fromSession).toBeUndefined();
  });

  it('keeps an open end open and rolls the end day over a month and year boundary', () => {
    expect(exportParams({ scope: 'dates', from: '', to: '2026-12-31' })).toEqual({ from: undefined, to: new Date(2027, 0, 1).toISOString() });
  });
});

describe('isExportRangeValid', () => {
  it('accepts the whole export without a range', () => {
    expect(isExportRangeValid({ scope: 'all', from: '', to: '' })).toBe(true);
  });

  it('needs at least one end of a filtered range', () => {
    expect(isExportRangeValid({ scope: 'dates', from: '', to: '' })).toBe(false);
    expect(isExportRangeValid({ scope: 'sessions', from: '', to: '' })).toBe(false);
    expect(isExportRangeValid({ scope: 'dates', from: '2026-03-01', to: '' })).toBe(true);
    expect(isExportRangeValid({ scope: 'sessions', from: '', to: '4' })).toBe(true);
  });

  it('rejects a start after the end, but allows a single day or session', () => {
    expect(isExportRangeValid({ scope: 'dates', from: '2026-03-02', to: '2026-03-01' })).toBe(false);
    expect(isExportRangeValid({ scope: 'dates', from: '2026-03-01', to: '2026-03-01' })).toBe(true);
    expect(isExportRangeValid({ scope: 'sessions', from: '10', to: '9' })).toBe(false);
    expect(isExportRangeValid({ scope: 'sessions', from: '9', to: '9' })).toBe(true);
  });

  it('compares session numbers as numbers and rejects non-positive or fractional ones', () => {
    expect(isExportRangeValid({ scope: 'sessions', from: '9', to: '10' })).toBe(true);
    expect(isExportRangeValid({ scope: 'sessions', from: '0', to: '5' })).toBe(false);
    expect(isExportRangeValid({ scope: 'sessions', from: '1.5', to: '' })).toBe(false);
  });
});
