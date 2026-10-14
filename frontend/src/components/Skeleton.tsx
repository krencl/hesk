/** Zástupné řádky při načítání (podobně jako „skeleton“ ve starém prototypu). */
export function ListSkeleton({ rows = 5, withImage = true }: { rows?: number; withImage?: boolean }) {
  return (
    <div className="skeleton" aria-busy="true" aria-label="Načítám">
      {Array.from({ length: rows }, (_, i) => (
        <div key={i} className={`skeleton-row${withImage ? ' with-image' : ''}`}>
          {withImage && <span className="skeleton-item skeleton-image" />}
          <span className="skeleton-lines">
            <span className="skeleton-item" style={{ width: `${45 + ((i * 17) % 30)}%`, height: '1.3rem' }} />
            <span className="skeleton-item" style={{ width: '55%' }} />
            <span className="skeleton-item" style={{ width: '25%' }} />
          </span>
        </div>
      ))}
    </div>
  );
}
