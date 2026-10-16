import type { ReactNode } from 'react';

/** Nadpis stránky – stejné písmo i odsazení jako název hry v záhlaví detailu hry (viz GameHeader). */
export function PageTitle({ children }: { children: ReactNode }) {
  return (
    <div className="page-head">
      <h2 className="page-title">{children}</h2>
    </div>
  );
}
