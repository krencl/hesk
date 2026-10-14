import { useEffect, useState } from 'react';

/** Hodnota zpožděná o `delay` ms od poslední změny (např. pro hledání při psaní). */
export function useDebounced<T>(value: T, delay = 250): T {
  const [debounced, setDebounced] = useState(value);
  useEffect(() => {
    const timer = setTimeout(() => setDebounced(value), delay);
    return () => clearTimeout(timer);
  }, [value, delay]);
  return debounced;
}
