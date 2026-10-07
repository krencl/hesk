import { useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { Link, NavLink, Outlet, useLocation } from 'react-router-dom';
import { useApp } from '../AppContext';
import { api } from '../api';
import { partyShortcut } from '../lib/partyShortcut';
import { Icon } from './Icon';

export function Layout() {
  const { me, activeParty, parties, setActiveParty } = useApp();
  const [menuOpen, setMenuOpen] = useState(false);
  const [changingParty, setChangingParty] = useState(false);
  const queryClient = useQueryClient();
  const location = useLocation();
  const [lastPath, setLastPath] = useState(location.pathname);
  if (lastPath !== location.pathname) {
    setLastPath(location.pathname);
    setMenuOpen(false);
    setChangingParty(false);
  }

  const logout = async () => {
    await api.logout();
    queryClient.clear();
    window.location.assign('/');
  };

  const shortcut = activeParty ? partyShortcut(activeParty.name) : '?';
  const toggleMenu = () => {
    setMenuOpen(!menuOpen);
    setChangingParty(false);
  };

  return (
    <div className="app">
      <header className="header">
        <Link to="/" aria-label="HESK – domů">
          <img src={`${import.meta.env.BASE_URL}logo.svg`} alt="HESK" className="header-logo" />
        </Link>
        <button
          type="button"
          className={`header-button size-${Math.min(shortcut.length, 2)}${menuOpen ? ' open' : ''}`}
          aria-label={menuOpen ? 'Zavřít menu' : `Menu – ${activeParty?.name ?? 'žádná skupina'}`}
          aria-expanded={menuOpen}
          onClick={toggleMenu}
        >
          {menuOpen ? <Icon name="close" /> : shortcut}
        </button>
      </header>

      <main>
        <Outlet />
      </main>

      <nav className={`menu${menuOpen ? ' visible' : ''}`} aria-label="Menu" aria-hidden={!menuOpen} inert={!menuOpen}>
        <div className="menu-header">
          <h2>{me.nickname}</h2>
        </div>
        <div className="menu-content">
          {changingParty ? (
            <>
              <h4 className="muted">Vyber skupinu</h4>
              <ul className="menu-list">
                {parties.filter((p) => p.id !== activeParty?.id).map((p) => (
                  <li key={p.id}>
                    <button type="button" onClick={() => { setActiveParty(p.id); toggleMenu(); }}>
                      {p.name} {p.role === 'admin' && <small className="muted">admin</small>}
                    </button>
                  </li>
                ))}
                <li><NavLink to="/parties">Vytvořit novou skupinu…</NavLink></li>
              </ul>
              <button type="button" className="btn small secondary" onClick={() => setChangingParty(false)}>Zrušit</button>
            </>
          ) : (
            <>
              <h4 className="primary">
                <small className="muted">Skupina</small>
                {activeParty?.name ?? 'Žádná skupina'}
                {parties.length > 1 && (
                  <button type="button" className="btn small outline" onClick={() => setChangingParty(true)}>Změnit</button>
                )}
              </h4>
              <ul className="menu-list">
                <li><NavLink to="/stats">Statistiky</NavLink></li>
                <li><NavLink to="/parties">Správa skupin</NavLink></li>
              </ul>
            </>
          )}

          <hr />
          <h4 className="primary">Navigace</h4>
          <ul className="menu-list">
            <li><NavLink to="/" end>Zápisy</NavLink></li>
            <li><NavLink to="/plays/new">Nový zápis</NavLink></li>
          </ul>

          <hr />
          <h4 className="primary">Správa</h4>
          <ul className="menu-list">
            <li><NavLink to="/games">Hry</NavLink></li>
            <li><NavLink to="/settings">Profil</NavLink></li>
          </ul>

          <hr />
          <ul className="menu-list">
            <li>
              <button type="button" className="muted" onClick={logout}><Icon name="logout" /> Odhlásit</button>
            </li>
          </ul>
        </div>
      </nav>
    </div>
  );
}
