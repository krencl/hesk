import { lazy, useEffect } from 'react';
import { Navigate, Route, Routes, useLocation } from 'react-router-dom';
import { clearAfterLoginPath, peekAfterLoginPath, rememberAfterLoginPath } from './lib/afterLogin';
import { AppProvider, useActiveParty, useCurrentUserQuery } from './AppContext';
import { Layout } from './components/Layout';
import { RequireParty } from './components/RequireParty';
import { RequireWriter } from './components/RequireWriter';
import { LoadingSplash, ServerDownSplash } from './components/Splash';
import { ToastProvider } from './components/Toasts';
import { TooltipLayer } from './components/TooltipLayer';
import { GameDetailPage } from './pages/GameDetailPage';
import { GameListPage } from './pages/GameListPage';
import { GamesPage } from './pages/GamesPage';
import { JoinPage } from './pages/JoinPage';
import { LoginPage } from './pages/LoginPage';
import { NewPlayPage } from './pages/NewPlayPage';
import { EditPlayPage } from './pages/EditPlayPage';
import { NicknamePage } from './pages/NicknamePage';
import { PartiesPage } from './pages/PartiesPage';
import { PartyDetailPage } from './pages/PartyDetailPage';
import { PartyEditPage } from './pages/PartyEditPage';
import { PartyGuestsPage } from './pages/PartyGuestsPage';
import { SettingsPage } from './pages/SettingsPage';
import { StatsPage } from './pages/StatsPage';

// Formulář hry a cheatsheet táhnou marked, DOMPurify a react-easy-crop; zbytek aplikace je nepotřebuje, proto se načítají až při otevření.
const GameFormPage = lazy(() => import('./pages/GameFormPage').then((m) => ({ default: m.GameFormPage })));
const GameCheatsheetPage = lazy(() => import('./pages/GameCheatsheetPage').then((m) => ({ default: m.GameCheatsheetPage })));

/** Starý odkaz `/stats` vede na statistiky aktivní party (`/parties/:id/stats`). */
function StatsRedirect() {
  const party = useActiveParty();
  return <Navigate to={`/parties/${party.id}/stats`} replace />;
}

export function App() {
  return (
    <ToastProvider>
      <AppContent />
      <TooltipLayer />
    </ToastProvider>
  );
}

function AppContent() {
  const { data: me, isPending, isError } = useCurrentUserQuery();
  const { pathname } = useLocation();

  // Cíl po přihlášení (pozvánka) se maže až po přesměrování, ne při renderu – StrictMode renderuje dvakrát.
  const pending = me?.hasNickname ? peekAfterLoginPath() : null;
  useEffect(() => {
    if (pending && pending === pathname) clearAfterLoginPath();
  }, [pending, pathname]);

  if (isPending) return <LoadingSplash />;
  if (isError) return <ServerDownSplash />;
  if (!me) {
    // Po přihlášení přes Google se vracíme na kořen; cílovou stránku (např. pozvánku) si proto pamatujeme.
    rememberAfterLoginPath(pathname);
    return <LoginPage />;
  }
  if (!me.hasNickname) return <NicknamePage first />;

  if (pending && pending !== pathname) return <Navigate to={pending} replace />;

  return (
    <AppProvider me={me}>
      <Routes>
        <Route element={<Layout />}>
          <Route path="/parties" element={<PartiesPage />} />
          <Route path="/parties/:id" element={<PartyDetailPage />} />
          <Route path="/parties/:id/edit" element={<PartyEditPage />} />
          <Route path="/parties/:id/guests" element={<PartyGuestsPage />} />
          <Route path="/parties/:id/stats" element={<StatsPage />} />
          <Route path="/join/:token" element={<JoinPage />} />
          <Route path="/settings" element={<SettingsPage />} />
          <Route element={<RequireParty />}>
            <Route path="/" element={<Navigate to="/games" replace />} />
            <Route path="/games" element={<GameListPage />} />
            <Route element={<RequireWriter />}>
              <Route path="/games/new" element={<GameFormPage />} />
              <Route path="/games/:id/edit" element={<GameFormPage />} />
              <Route path="/manage/games" element={<GamesPage />} />
            </Route>
            <Route path="/games/:id" element={<GameDetailPage />} />
            <Route path="/games/:id/cheatsheet" element={<GameCheatsheetPage />} />
            <Route path="/games/:id/new-play" element={<NewPlayPage />} />
            <Route path="/plays/:id/edit" element={<EditPlayPage />} />
            <Route path="/stats" element={<StatsRedirect />} />
          </Route>
          <Route path="*" element={<Navigate to="/" replace />} />
        </Route>
      </Routes>
    </AppProvider>
  );
}
