import { createContext, type ReactNode, useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react';
import { Icon } from './Icon';

/** Jak dlouho zpráva visí, než sama zmizí. */
export const TOAST_DURATION_MS = 6000;
/** Délka animace odjezdu; po ní se zpráva odstraní z DOM. */
const LEAVE_MS = 200;

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
          <ToastItem key={t.id} toast={t} onDismiss={() => dismiss(t.id)} />
        ))}
      </div>
    </ToastContext.Provider>
  );
}

function ToastItem({ toast, onDismiss }: { toast: Toast; onDismiss: () => void }) {
  const [paused, setPaused] = useState(false);

  // Po najetí myší / zaostření zpráva nemizí, ať se dá přečíst.
  useEffect(() => {
    if (paused || toast.leaving) return;
    const timer = window.setTimeout(onDismiss, TOAST_DURATION_MS);
    return () => window.clearTimeout(timer);
  }, [paused, toast.leaving, onDismiss]);

  return (
    <div
      className={`toast${toast.leaving ? ' leaving' : ''}`}
      role="alert"
      onMouseEnter={() => setPaused(true)}
      onMouseLeave={() => setPaused(false)}
      onFocus={() => setPaused(true)}
      onBlur={() => setPaused(false)}
    >
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
      <button type="button" className="toast-close" aria-label="Zavřít oznámení" onClick={onDismiss}>
        <Icon name="close" />
      </button>
    </div>
  );
}
