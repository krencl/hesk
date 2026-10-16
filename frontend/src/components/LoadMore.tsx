import { useEffect, useRef } from 'react';

interface Props {
  hasMore: boolean;
  loading: boolean;
  /** Poslední načtení selhalo: automatické dotahování se zastaví a nabídne se tlačítko „Zkusit znovu“. */
  failed?: boolean;
  onLoad: () => void;
  /** Popisek tlačítka v prostředí bez IntersectionObserver. */
  label?: string;
}

/**
 * Dotahuje další stránky seznamu při rolování: neviditelný hlídač na konci seznamu zavolá `onLoad`,
 * jakmile se přiblíží do výhledu (a opakuje to, dokud nevyplní obrazovku). Bez IntersectionObserveru
 * a po chybě se zobrazí tlačítko.
 */
export function LoadMore({ hasMore, loading, failed = false, onLoad, label = 'Načíst další' }: Props) {
  const sentinel = useRef<HTMLDivElement>(null);
  const latest = useRef(onLoad);
  latest.current = onLoad;
  const observable = typeof IntersectionObserver !== 'undefined' && !failed;

  useEffect(() => {
    const el = sentinel.current;
    if (!observable || !hasMore || loading || !el) return;
    const observer = new IntersectionObserver((entries) => {
      if (entries.some((e) => e.isIntersecting)) latest.current();
    }, { rootMargin: '400px 0px' });
    observer.observe(el);
    return () => observer.disconnect();
  }, [observable, hasMore, loading]);

  if (!hasMore) return null;
  if (!observable) {
    return (
      <button type="button" className="btn light block more" disabled={loading} onClick={onLoad}>
        {failed ? 'Zkusit znovu' : label}
      </button>
    );
  }
  return (
    <div ref={sentinel} className="load-more" role="status" aria-live="polite">
      {loading && <span className="muted">Načítám…</span>}
    </div>
  );
}
