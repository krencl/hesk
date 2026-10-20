import type { ReactNode } from 'react';

/**
 * Hlavní tlačítka stránky (CTA): mřížka 2×2, vždy stejně velká a nejvýše čtyři.
 * Pořadí potomků určuje pozici: 1. vlevo nahoře (primární, `btn`), 2. vpravo nahoře (sekundární, `btn outline`),
 * 3. vpravo dole (terciální, `btn outline-secondary`), 4. vlevo dole (poslední).
 *
 * Lišta je přichycená ke spodku obrazovky (`position: sticky`): na mobilu vždy dole, na desktopu jen tehdy,
 * když je stránka delší než obrazovka. Aby měla kam se přichytit, musí být přímo ve stránce (`.page`) nebo ve
 * formuláři, ne v malém bloku `.pad` jen kolem sebe. `inline` je tlačítko uvnitř obsahu (např. v rozbaleném zápisu),
 * které se nepřichycuje.
 */
export function Actions({ children, inline = false }: { children: ReactNode; inline?: boolean }) {
  return <div className={`actions${inline ? ' inline' : ''}`}>{children}</div>;
}
