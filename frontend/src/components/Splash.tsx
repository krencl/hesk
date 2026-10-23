/** Celostránková hláška před startem aplikace (načítání dat, nedostupný server). */
export function LoadingSplash() {
  return <div className="splash">Načítám…</div>;
}

export function ServerDownSplash() {
  return <div className="splash">Server je nedostupný. Zkus to prosím později.</div>;
}
