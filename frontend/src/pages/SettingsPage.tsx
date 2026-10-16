import { useApp } from '../AppContext';
import { PageTitle } from '../components/PageTitle';
import { NicknamePage } from './NicknamePage';

export function SettingsPage() {
  const { me } = useApp();
  return (
    <section className="page">
      <PageTitle>Nastavení</PageTitle>
      <div className="pad">
        <p className="muted">{me.email}</p>
        <NicknamePage initial={me.nickname ?? ''} />
      </div>
    </section>
  );
}
