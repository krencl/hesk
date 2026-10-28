import { useIsFetching, useIsMutating, useMutation, useQueryClient } from '@tanstack/react-query';
import { Suspense, useState } from 'react';
import { Link, NavLink, Outlet, useLocation, useNavigate } from 'react-router-dom';
import { useApp } from '../AppContext';
import { api } from '../api';
import { pathForSwitchedParty } from '../lib/partyPath';
import { canWrite } from '../lib/roles';
import { ErrorMessage } from './ErrorMessage';
import { Icon } from './Icon';
import { Logo } from './Logo';
import { ListSkeleton } from './Skeleton';

export function Layout() {
  const { me, activeParty, parties, setActiveParty } = useApp();
  const [menuOpen, setMenuOpen] = useState(false);
  const [changingParty, setChangingParty] = useState(false);
  const queryClient = useQueryClient();
  const loading = useIsFetching() + useIsMutating() > 0;
  const location = useLocation();
  const navigate = useNavigate();
  const [lastPath, setLastPath] = useState(location.pathname);
  if (lastPath !== location.pathname) {
    setLastPath(location.pathname);
    setMenuOpen(false);
    setChangingParty(false);
  }

  // Při chybě (server nedostupný) se uživatel neodhlásí, takže se ukáže hláška místo tichého nic.
  const logout = useMutation({
    mutationFn: api.logout,
    onSuccess: () => {
      queryClient.clear();
      window.location.assign('/');
    },
  });

  const shortcut = activeParty ? activeParty.shortName : '?';
  const toggleMenu = () => {
    setMenuOpen(!menuOpen);
    setChangingParty(false);
  };
  // Na stránce správy party (/parties/:id) přepnutí party přepne i stránku, jinak by zůstala u původní party.
  const switchParty = (id: number) => {
    setActiveParty(id);
    const path = pathForSwitchedParty(location.pathname, id);
    if (path) navigate(path);
    closeMenu();
  };
  // Odkaz na stránku, na které už jsem, nezmění adresu, a menu by tak zůstalo otevřené – zavřeme ho vždy.
  const closeMenu = () => {
    setMenuOpen(false);
    setChangingParty(false);
  };

  return (
    <div className="app">
      <header className="header">
        <Link to="/" aria-label="HESK – domů">
          <Logo className="header-logo" />
        </Link>
        <button
          type="button"
          className={`header-button size-${Math.min(shortcut.length, 3)}${menuOpen ? ' open' : ''}${loading ? ' loading' : ''}`}
          aria-label={menuOpen ? 'Zavřít menu' : `Menu – ${activeParty?.name ?? 'žádná parta'}`}
          aria-expanded={menuOpen}
          onClick={toggleMenu}
        >
          {menuOpen ? <Icon name="close" /> : shortcut}
        </button>
      </header>

      <main>
        <Suspense fallback={<ListSkeleton rows={3} withImage={false} />}>
          <Outlet />
        </Suspense>
      </main>

      <nav className={`menu${menuOpen ? ' visible' : ''}`} aria-label="Menu" aria-hidden={!menuOpen} inert={!menuOpen}>
        <div className="menu-header">
          <h2>{me.nickname}</h2>
        </div>
        <div className="menu-content">
          {changingParty ? (
            <>
              <h4 className="primary">Vyber partu</h4>
              <ul className="menu-list">
                {parties.filter((p) => p.id !== activeParty?.id).map((p) => (
                  <li key={p.id}>
                    <button type="button" onClick={() => switchParty(p.id)}>
                      {p.name}
                    </button>
                  </li>
                ))}
              </ul>
              <div className="menu-actions">
                <Link className="btn small" to="/parties" onClick={closeMenu}>Vytvořit novou partu</Link>
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
                <li><NavLink to={activeParty ? `/parties/${activeParty.id}/stats` : '/stats'} onClick={closeMenu}>Statistiky</NavLink></li>
                {activeParty && <li><NavLink to={`/parties/${activeParty.id}`} className={({ isActive }) => (isActive && !location.pathname.endsWith('/stats') ? 'active' : undefined)} onClick={closeMenu}>Spravovat</NavLink></li>}
              </ul>
            </>
          )}

          <hr />
          <h4 className="primary">Menu</h4>
          <ul className="menu-list">
            <li><NavLink to="/" end onClick={closeMenu}>Seznam her</NavLink></li>
            {activeParty && <li><NavLink to="/my-stats" onClick={closeMenu}>Moje statistiky</NavLink></li>}
          </ul>

          <hr />
          <h4 className="primary">Správa</h4>
          <ul className="menu-list">
            <li><NavLink to="/parties" end onClick={closeMenu}>Spravovat party</NavLink></li>
            {activeParty && canWrite(activeParty.role) && <li><NavLink to="/manage/games" onClick={closeMenu}>Spravovat hry</NavLink></li>}
            <li><NavLink to="/settings" onClick={closeMenu}>Nastavení</NavLink></li>
          </ul>

          <hr />
          <p className="menu-logout">
            <button type="button" disabled={logout.isPending} onClick={() => logout.mutate()}>Odhlásit se</button>
            <ErrorMessage error={logout.error} />
          </p>
        </div>
        <footer className="menu-footer">
          <strong>HESK {__APP_VERSION__}</strong>
          <span>© Krencl</span>
          <span>Aplikace slouží výhradně pro přátelské zapisování výsledků.</span>
        </footer>
      </nav>
    </div>
  );
}
