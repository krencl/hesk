import type { Party } from '../api/types';

const KEY = 'hesk.activePartyId';

export function loadActivePartyId(): number | null {
  try {
    const raw = localStorage.getItem(KEY);
    const id = raw === null ? NaN : Number(raw);
    return Number.isInteger(id) ? id : null;
  } catch {
    return null;
  }
}

export function saveActivePartyId(id: number | null): void {
  try {
    if (id === null) localStorage.removeItem(KEY);
    else localStorage.setItem(KEY, String(id));
  } catch {
    // úložiště nemusí být dostupné (privátní režim) – aktivní skupina se pak jen nepamatuje
  }
}

/** Zvolí aktivní skupinu: uloženou, pokud v seznamu pořád je, jinak první dostupnou. */
export function resolveActiveParty(parties: Party[], storedId: number | null): Party | null {
  return parties.find((p) => p.id === storedId) ?? parties[0] ?? null;
}
