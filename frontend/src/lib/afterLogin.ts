const KEY = 'hesk.afterLogin';

/** Zapamatuje cestu pozvánky (/join/...) přes přesměrování na přihlášení; ostatní cesty ignoruje. */
export function rememberAfterLoginPath(path: string): void {
  try {
    if (path.startsWith('/join/')) sessionStorage.setItem(KEY, path);
  } catch {
    // sessionStorage nemusí být dostupné; pozvánku pak stačí otevřít znovu
  }
}

/** Zapamatovaná cesta, aniž by se smazala (bezpečné volat při renderu). */
export function peekAfterLoginPath(): string | null {
  try {
    return sessionStorage.getItem(KEY);
  } catch {
    return null;
  }
}

/** Token pozvánky ze zapamatované cesty `/join/<token>`; s ním smí nový uživatel při přihlášení založit účet (registrace je jinak uzavřená). */
export function peekInvitationToken(): string | null {
  const match = /^\/join\/([^/?#]+)/.exec(peekAfterLoginPath() ?? '');
  return match ? decodeURIComponent(match[1]) : null;
}

/** Smaže zapamatovanou cestu; volat až po přesměrování (efekt), ne při renderu. */
export function clearAfterLoginPath(): void {
  try {
    sessionStorage.removeItem(KEY);
  } catch {
    // nic se nestane, cesta se jen při dalším přihlášení použije znovu
  }
}
