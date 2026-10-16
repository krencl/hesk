import { Navigate, Route, Routes, useLocation } from 'react-router-dom';
import { consumeAfterLoginPath, rememberAfterLoginPath } from './lib/afterLogin';
import { AppProvider, useCurrentUserQuery } from './AppContext';
import { Layout } from './components/Layout';
import { RequireParty } from './components/RequireParty';
import { GameCheatsheetPage } from './pages/GameCheatsheetPage';
import { GameDetailPage } from './pages/GameDetailPage';
import { GameFormPage } from './pages/GameFormPage';
import { GameListPage } from './pages/GameListPage';
import { GamesPage } from './pages/GamesPage';
import { JoinPage } from './pages/JoinPage';
import { LoginPage } from './pages/LoginPage';
import { NewPlayPage } from './pages/NewPlayPage';
import { PlayDetailPage } from './pages/PlayDetailPage';
import { NicknamePage } from './pages/NicknamePage';
import { PartiesPage } from './pages/PartiesPage';
import { PartyDetailPage } from './pages/PartyDetailPage';
import { SettingsPage } from './pages/SettingsPage';
import { StatsPage } from './pages/StatsPage';

export function App() {
  const { data: me, isPending, isError } = useCurrentUserQuery();
  const { pathname } = useLocation();

  if (isPending) return <div className="splash">Načítám…</div>;
  if (isError) return <div className="splash">Server je nedostupný. Zkus to prosím později.</div>;
  if (!me) {
    // Po přihlášení přes Google se vracíme na kořen; cílovou stránku (např. pozvánku) si proto pamatujeme.
    rememberAfterLoginPath(pathname);
    return <LoginPage />;
  }
  if (!me.hasNickname) return <NicknamePage first />;

  const pending = consumeAfterLoginPath();
  if (pending && pending !== pathname) return <Navigate to={pending} replace />;

  return (
    <AppProvider me={me}>
      <Routes>
        <Route element={<Layout />}>
          <Route path="/parties" element={<PartiesPage />} />
          <Route path="/parties/:id" element={<PartyDetailPage />} />
          <Route path="/join/:token" element={<JoinPage />} />
          <Route path="/settings" element={<SettingsPage />} />
          <Route element={<RequireParty />}>
            <Route path="/" element={<Navigate to="/games" replace />} />
            <Route path="/games" element={<GameListPage />} />
            <Route path="/games/new" element={<GameFormPage />} />
            <Route path="/games/:id" element={<GameDetailPage />} />
            <Route path="/games/:id/cheatsheet" element={<GameCheatsheetPage />} />
            <Route path="/games/:id/new-play" element={<NewPlayPage />} />
            <Route path="/games/:id/edit" element={<GameFormPage />} />
            <Route path="/manage/games" element={<GamesPage />} />
            <Route path="/plays/:id" element={<PlayDetailPage />} />
            <Route path="/stats" element={<StatsPage />} />
          </Route>
          <Route path="*" element={<Navigate to="/" replace />} />
        </Route>
      </Routes>
    </AppProvider>
  );
}
