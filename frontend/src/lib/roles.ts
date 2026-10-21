import type { PartyRole } from '../api/types';

export const ROLE_LABELS: Record<PartyRole, string> = {
  admin: 'Správce',
  writer: 'Zapisovač',
  member: 'Člen',
};

/** Pořadí v nabídce role: od nejmenších práv po největší. */
export const ROLE_ORDER: PartyRole[] = ['member', 'writer', 'admin'];

/** Zápisy her smí přidávat, upravovat a mazat jen admin a zapisovač (člen je jen čte). */
export const canWrite = (role: PartyRole): boolean => role === 'admin' || role === 'writer';
