/** Kam vede „Zrušit“ na stránkách správy party: celá správa party se zavře a vrátíme se do aplikace (seznam her). */
export const LEAVE_PARTY_MANAGEMENT_PATH = '/games';

/**
 * Stránka správy party (`/parties/:id`, `/parties/:id/guests`, `/parties/:id/edit`) a statistiky (`/parties/:id/stats`) po přepnutí na jinou partu: stejná stránka pod novou partou
 * (filtr statistik se zahodí, patří k původní partě). Jiné stránky (seznam her, …) se řídí aktivní partou samy, tam se adresa nemění → `null`.
 */
export function pathForSwitchedParty(pathname: string, partyId: number): string | null {
  const match = /^\/parties\/\d+(\/(?:edit|guests|stats))?\/?$/.exec(pathname);
  return match ? `/parties/${partyId}${match[1] ?? ''}` : null;
}
