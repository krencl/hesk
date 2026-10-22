import { useEffect } from 'react';
import { ApiError } from '../api/client';
import { useToasts } from './Toasts';

const FALLBACK = 'Něco se pokazilo. Zkus to prosím znovu.';

/**
 * Chyba z API. Standardně se ukáže jako „toast“ (zpráva shora, která za chvíli zmizí); komponenta sama nic nevykresluje.
 * `inline` je pro stránky, které kvůli chybě nemají jiný obsah (nenačetl se detail) – tam zpráva musí zůstat na stránce.
 */
export function ErrorMessage({ error, inline = false }: { error: unknown; inline?: boolean }) {
  const { showError } = useToasts();
  const e = error instanceof ApiError ? error : null;
  const message = e?.message || FALLBACK;
  const details = e ? e.violations.map((v) => v.message) : [];

  useEffect(() => {
    if (error && !inline) showError(message, details);
    // `details` se odvozuje z `error`; efekt se má spustit jen při nové chybě
  }, [error, inline]);

  if (!error || !inline) return null;
  return (
    <div className="error" role="alert">
      {message}
      {details.length > 0 && (
        <ul>
          {details.map((d, i) => (
            <li key={i}>{d}</li>
          ))}
        </ul>
      )}
    </div>
  );
}
