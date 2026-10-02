const KEY = 'hesk.afterLogin';

/** Zapamatuje cestu pozvánky (/join/...) přes přesměrování na přihlášení; ostatní cesty ignoruje. */
export function rememberAfterLoginPath(path: string): void {
  try {
    if (path.startsWith('/join/')) sessionStorage.setItem(KEY, path);
  } catch {
    // sessionStorage nemusí být dostupné; pozvánku pak stačí otevřít znovu
  }
}

export function consumeAfterLoginPath(): string | null {
  try {
    const path = sessionStorage.getItem(KEY);
    if (path) sessionStorage.removeItem(KEY);
    return path;
  } catch {
    return null;
  }
}
