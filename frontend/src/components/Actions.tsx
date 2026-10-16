import type { ReactNode } from 'react';

/**
 * Hlavní tlačítka stránky (CTA): mřížka 2×2, vždy stejně velká a nejvýše čtyři.
 * Pořadí potomků určuje pozici: 1. vlevo nahoře (primární, `btn`), 2. vpravo nahoře (sekundární, `btn outline`),
 * 3. vpravo dole (terciální, `btn outline-secondary`), 4. vlevo dole (poslední).
 */
export function Actions({ children }: { children: ReactNode }) {
  return <div className="actions">{children}</div>;
}
