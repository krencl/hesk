/** What a party export contains: everything, plays in a date range, or plays of sessions in a number range (ADR 0033). */
export type ExportScope = 'all' | 'dates' | 'sessions';

export interface ExportRange {
  scope: ExportScope;
  /** `YYYY-MM-DD` for `dates`, a session number for `sessions`; empty = open end. */
  from: string;
  to: string;
}

export interface ExportParams {
  from?: string;
  to?: string;
  fromSession?: string;
  toSession?: string;
}

/** A day (`YYYY-MM-DD`) in the viewer's time zone as an ISO instant; `plusDays: 1` gives the (exclusive) end of that day. */
function localDay(day: string, plusDays = 0): string {
  const date = new Date(`${day}T00:00`);
  date.setDate(date.getDate() + plusDays);
  return date.toISOString();
}

/** API query for the range. Dates are sent as instants (local midnight, end day exclusive) so the server's time zone does not matter. */
export function exportParams({ scope, from, to }: ExportRange): ExportParams {
  if (scope === 'dates') return { from: from ? localDay(from) : undefined, to: to ? localDay(to, 1) : undefined };
  if (scope === 'sessions') return { fromSession: from || undefined, toSession: to || undefined };
  return {};
}

/** A filtered export needs at least one end, and the start may not be after the end. */
export function isExportRangeValid({ scope, from, to }: ExportRange): boolean {
  if (scope === 'all') return true;
  if (!from && !to) return false;
  if (scope === 'sessions') {
    const whole = (value: string) => value === '' || (/^\d+$/.test(value) && Number(value) >= 1);
    return whole(from) && whole(to) && (!from || !to || Number(from) <= Number(to));
  }
  return !from || !to || from <= to;
}
