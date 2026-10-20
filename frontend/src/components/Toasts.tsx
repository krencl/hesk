import { createContext, type CSSProperties, type ReactNode, useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react';
import { Icon } from './Icon';

/** Jak dlouho zpráva visí, než sama zmizí: základ + sekunda za každou podrobnost (delší hlášení se čte déle). */
export const TOAST_BASE_MS = 4000;
export const toastDuration = (detailCount: number): number => TOAST_BASE_MS + 1000 * detailCount;
/** Délka animace odjezdu (musí odpovídat `.toast-slot` ve stylech); po ní se zpráva odstraní z DOM. */
const LEAVE_MS = 250;

interface Toast {
  id: number;
  message: string;
  details: string[];
  leaving: boolean;
}

interface ToastApi {
  /** Zobrazí chybovou zprávu (nepovinně se seznamem podrobností); stejný text, který už visí, se jen obnoví. */
  showError: (message: string, details?: string[]) => void;
}

const ToastContext = createContext<ToastApi | null>(null);

export function useToasts(): ToastApi {
  const api = useContext(ToastContext);
  if (!api) throw new Error('useToasts musí být uvnitř ToastProvider');
  return api;
}

/** Chybové „toast“ zprávy shora dolů pod záhlavím; zobrazit jich lze víc najednou, každá má křížek a po chvíli zmizí. */
export function ToastProvider({ children }: { children: ReactNode }) {
  const [toasts, setToasts] = useState<Toast[]>([]);
  const nextId = useRef(1);

  const remove = useCallback((id: number) => setToasts((all) => all.filter((t) => t.id !== id)), []);
  const dismiss = useCallback(
    (id: number) => {
      setToasts((all) => all.map((t) => (t.id === id ? { ...t, leaving: true } : t)));
      window.setTimeout(() => remove(id), LEAVE_MS);
    },
    [remove],
  );
  const showError = useCallback((message: string, details: string[] = []) => {
    // stejná zpráva (např. opakované kliknutí na „Uložit“) se nenaskládá znovu, jen se obnoví
    setToasts((all) => [...all.filter((t) => !(t.message === message && !t.leaving)), { id: nextId.current++, message, details, leaving: false }]);
  }, []);

  const api = useMemo(() => ({ showError }), [showError]);

  return (
    <ToastContext.Provider value={api}>
      {children}
      <div className="toasts" role="region" aria-label="Oznámení">
        {toasts.map((t) => (
          <ToastItem key={t.id} toast={t} onDismiss={dismiss} />
        ))}
      </div>
    </ToastContext.Provider>
  );
}

function ToastItem({ toast, onDismiss }: { toast: Toast; onDismiss: (id: number) => void }) {
  const [paused, setPaused] = useState(false);
  const duration = toastDuration(toast.details.length);
  const remaining = useRef(duration);

  // Odpočet běží, dokud zpráva není pod myší / zaostřená; po pokračování dobíhá zbytek (stejně jako proužek dole).
  useEffect(() => {
    if (paused || toast.leaving) return;
    const startedAt = Date.now();
    const timer = window.setTimeout(() => onDismiss(toast.id), remaining.current);
    return () => {
      window.clearTimeout(timer);
      remaining.current -= Date.now() - startedAt;
    };
  }, [paused, toast.leaving, toast.id, onDismiss]);

  return (
    // slot se při odjezdu svinuje na nulovou výšku, takže zprávy pod ním plynule sjedou nahoru
    <div className={`toast-slot${toast.leaving ? ' leaving' : ''}`}>
      <div
        className={`toast${paused ? ' paused' : ''}`}
        role="alert"
        style={{ '--toast-duration': `${duration}ms` } as CSSProperties}
        onMouseEnter={() => setPaused(true)}
        onMouseLeave={() => setPaused(false)}
        onFocus={() => setPaused(true)}
        onBlur={() => setPaused(false)}
      >
        <div className="toast-body">
          <div className="toast-text">
            {toast.message}
            {toast.details.length > 0 && (
              <ul>
                {toast.details.map((d, i) => (
                  <li key={i}>{d}</li>
                ))}
              </ul>
            )}
          </div>
          <button type="button" className="toast-close" aria-label="Zavřít oznámení" onClick={() => onDismiss(toast.id)}>
            <Icon name="close" />
          </button>
        </div>
        <div className="toast-bar" aria-hidden />
      </div>
    </div>
  );
}
