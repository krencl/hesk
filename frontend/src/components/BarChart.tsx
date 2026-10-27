import { Link } from 'react-router-dom';

export interface BarItem {
  key: number | string;
  label: string;
  value: number;
  /** Hodnota, která odpovídá plné šířce sloupce (např. počet všech her); bez ní je plná šířka nejvyšší hodnota grafu. */
  total?: number;
  /** Text za sloupcem místo hodnoty (např. „2/2“). */
  valueText?: string;
  /** Popisek je odkaz. */
  to?: string;
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
    <ul className={`bars${shown.some((i) => i.valueText) ? ' wide' : ''}`}>
      {shown.map((i, index) => (
        <li key={i.key}>
          <span className="bar-label">{i.to ? <Link to={i.to}>{i.label}</Link> : i.label}</span>
          <span className="bar-track">
            <span className={`bar-fill${index >= TOP_COUNT ? ' rest' : ''}`} style={{ width: `${(i.value / (i.total || max)) * 100}%`, animationDelay: `${index * 50}ms` }} role="img" aria-label={`${i.label}: ${i.valueText ?? i.value}`} />
          </span>
          <span className="bar-value">{i.valueText ?? i.value}</span>
        </li>
      ))}
    </ul>
  );
}
