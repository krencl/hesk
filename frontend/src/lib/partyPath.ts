/**
 * Stránka správy party (`/parties/:id`, `/parties/:id/edit`) po přepnutí na jinou partu: stejná stránka pod novou partou.
 * Jiné stránky (seznam her, statistiky, …) se řídí aktivní partou samy, tam se adresa nemění → `null`.
 */
export function pathForSwitchedParty(pathname: string, partyId: number): string | null {
  const match = /^\/parties\/\d+(\/edit)?\/?$/.exec(pathname);
  return match ? `/parties/${partyId}${match[1] ?? ''}` : null;
}
