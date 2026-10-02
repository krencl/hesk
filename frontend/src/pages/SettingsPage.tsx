import { useApp } from '../AppContext';
import { NicknamePage } from './NicknamePage';

export function SettingsPage() {
  const { me } = useApp();
  return (
    <section>
      <h2>Profil</h2>
      <p className="muted">{me.email}</p>
      <NicknamePage initial={me.nickname ?? ''} />
    </section>
  );
}
