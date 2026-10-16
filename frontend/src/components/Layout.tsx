import { useIsFetching, useIsMutating, useQueryClient } from '@tanstack/react-query';
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
  const loading = useIsFetching() + useIsMutating() > 0;
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
          className={`header-button size-${Math.min(shortcut.length, 2)}${menuOpen ? ' open' : ''}${loading ? ' loading' : ''}`}
          aria-label={menuOpen ? 'Zavřít menu' : `Menu – ${activeParty?.name ?? 'žádná parta'}`}
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
              <h4 className="muted">Vyber partu</h4>
              <ul className="menu-list">
                {parties.filter((p) => p.id !== activeParty?.id).map((p) => (
                  <li key={p.id}>
                    <button type="button" onClick={() => { setActiveParty(p.id); toggleMenu(); }}>
                      {p.name} {p.role === 'admin' && <small className="muted">admin</small>}
                    </button>
                  </li>
                ))}
              </ul>
              <div className="menu-actions">
                <Link className="btn small" to="/parties">Vytvořit novou partu</Link>
                <button type="button" className="btn small secondary" onClick={() => setChangingParty(false)}>Zrušit</button>
              </div>
            </>
          ) : (
            <>
              <h4 className="primary">
                <small className="muted">Parta</small>
                {activeParty?.name ?? 'Žádná parta'}
                <button type="button" className="btn small outline" onClick={() => setChangingParty(true)}>Přepnout</button>
              </h4>
              <ul className="menu-list">
                {activeParty && <li><NavLink to={`/parties/${activeParty.id}`}>Zobrazit detail</NavLink></li>}
                <li><NavLink to="/stats">Statistiky</NavLink></li>
              </ul>
            </>
          )}

          <hr />
          <h4 className="primary">Menu</h4>
          <ul className="menu-list">
            <li><NavLink to="/games" end>Seznam her</NavLink></li>
          </ul>

          <hr />
          <h4 className="primary">Správa</h4>
          <ul className="menu-list">
            <li><NavLink to="/parties" end>Spravovat party</NavLink></li>
            <li><NavLink to="/manage/games">Spravovat hry</NavLink></li>
            <li><NavLink to="/settings">Nastavení</NavLink></li>
          </ul>

          <hr />
          <p className="menu-logout">
            <button type="button" onClick={logout}>Odhlásit se</button>
          </p>
        </div>
      </nav>
    </div>
  );
}
