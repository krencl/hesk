export interface BarItem {
  key: number | string;
  label: string;
  value: number;
}

/** Jednoduchý vodorovný sloupcový graf bez závislostí. */
export function BarChart({ items }: { items: BarItem[] }) {
  if (items.length === 0) return <p className="muted">Zatím žádná data.</p>;
  const max = Math.max(...items.map((i) => i.value), 1);
  return (
    <ul className="bars">
      {items.map((i) => (
        <li key={i.key}>
          <span className="bar-label">{i.label}</span>
          <span className="bar-track">
            <span className="bar-fill" style={{ width: `${(i.value / max) * 100}%` }} role="img" aria-label={`${i.label}: ${i.value}`} />
          </span>
          <span className="bar-value">{i.value}</span>
        </li>
      ))}
    </ul>
  );
}
