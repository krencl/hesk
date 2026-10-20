import type { ReactNode } from 'react';

/**
 * Nadpis stránky – stejné písmo i odsazení jako název hry v záhlaví detailu hry (viz GameHeader).
 * `label` je malý sekundární popisek před textem (jako „Parta“ v menu).
 */
export function PageTitle({ label, children }: { label?: string; children: ReactNode }) {
  return (
    <div className="page-head">
      <h2 className="page-title">{label && <><small className="page-title-label">{label}</small>{' '}</>}{children}</h2>
    </div>
  );
}
