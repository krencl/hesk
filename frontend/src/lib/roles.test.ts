import { describe, expect, it } from 'vitest';
import { canWrite, ROLE_LABELS } from './roles';

describe('roles', () => {
  it('lets only admins and writers write plays', () => {
    expect(canWrite('admin')).toBe(true);
    expect(canWrite('writer')).toBe(true);
    expect(canWrite('member')).toBe(false);
  });

  it('has Czech labels', () => {
    expect(ROLE_LABELS).toEqual({ admin: 'Admin', writer: 'Zapisovač', member: 'Člen' });
  });
});
