export interface BarItem {
  key: number | string;
  label: string;
  value: number;
}

const TOP_COUNT = 3;

/**
 * Jednoduchý vodorovný sloupcový graf bez závislostí. Položky s hodnotou 0 se nezobrazují; sloupce se při zobrazení
 * rozrostou zleva (CSS animace, postupně od prvního), při změně dat se sloupec plynule prodlouží/zkrátí. První tři položky
 * (pořadí určuje volající) mají plnou modrou, ostatní slabší odstín.
 */
export function BarChart({ items }: { items: BarItem[] }) {
  const shown = items.filter((i) => i.value > 0);
  if (shown.length === 0) return <p className="muted">Zatím žádná data.</p>;
  const max = Math.max(...shown.map((i) => i.value));
  return (
    <ul className="bars">
      {shown.map((i, index) => (
        <li key={i.key}>
          <span className="bar-label">{i.label}</span>
          <span className="bar-track">
            <span className={`bar-fill${index >= TOP_COUNT ? ' rest' : ''}`} style={{ width: `${(i.value / max) * 100}%`, animationDelay: `${index * 50}ms` }} role="img" aria-label={`${i.label}: ${i.value}`} />
          </span>
          <span className="bar-value">{i.value}</span>
        </li>
      ))}
    </ul>
  );
}
