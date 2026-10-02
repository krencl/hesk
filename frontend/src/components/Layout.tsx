import { useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { Link, NavLink, Outlet, useLocation } from 'react-router-dom';
import { useApp } from '../AppContext';
import { api } from '../api';

export function Layout() {
  const { activeParty, parties, setActiveParty } = useApp();
  const [panel, setPanel] = useState<'party' | 'menu' | null>(null);
  const queryClient = useQueryClient();
  const location = useLocation();
  const [lastPath, setLastPath] = useState(location.pathname);
  if (lastPath !== location.pathname) {
    setLastPath(location.pathname);
    setPanel(null);
  }

  const logout = async () => {
    await api.logout();
    queryClient.clear();
    window.location.assign('/');
  };

  return (
    <div className="app">
      <header className="topbar">
        <Link to="/" className="logo" aria-label="HESK – domů">HESK</Link>

        <button type="button" className="party-switch" aria-expanded={panel === 'party'} onClick={() => setPanel(panel === 'party' ? null : 'party')}>
          {activeParty?.name ?? 'Žádná skupina'} <span aria-hidden>▾</span>
        </button>

        <button type="button" className="hamburger" aria-label="Menu" aria-expanded={panel === 'menu'} onClick={() => setPanel(panel === 'menu' ? null : 'menu')}>
          <span /><span /><span />
        </button>

        {panel === 'party' && (
          <nav className="dropdown" aria-label="Přepnout skupinu">
            {parties.map((p) => (
              <button key={p.id} type="button" className={p.id === activeParty?.id ? 'active' : ''} onClick={() => { setActiveParty(p.id); setPanel(null); }}>
                {p.name}
              </button>
            ))}
            <Link to="/parties">Správa skupin…</Link>
          </nav>
        )}

        {panel === 'menu' && (
          <nav className="dropdown" aria-label="Menu">
            <h3>Nastavení</h3>
            <NavLink to="/settings">Profil</NavLink>
            <NavLink to="/games">Hry</NavLink>
            <h3>Skupiny</h3>
            <NavLink to="/parties">Správa skupin</NavLink>
            <NavLink to="/stats">Statistiky</NavLink>
            <h3>Účet</h3>
            <button type="button" onClick={logout}>Odhlásit</button>
          </nav>
        )}
      </header>
      <main>
        <Outlet />
      </main>
    </div>
  );
}
