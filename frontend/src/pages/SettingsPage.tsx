import { useApp } from '../AppContext';
import { PageTitle } from '../components/PageTitle';
import { usePartyDetail } from '../lib/usePartyDetail';
import { NicknamePage } from './NicknamePage';
import { PartyNicknameForm } from './PartyNicknameForm';

export function SettingsPage() {
  const { me, activeParty } = useApp();
  return (
    <section className="page">
      <PageTitle>Nastavení</PageTitle>
      <div className="pad">
        <p className="muted">{me.email}</p>
        <NicknamePage initial={me.nickname ?? ''} hint="Defaultní nick, použije se pro nové party" />
        {activeParty && <PartyNickname partyId={activeParty.id} />}
      </div>
    </section>
  );
}

/** Nick v aktivní partě; formulář se vykreslí, až když je známý aktuální nick (detail party). */
function PartyNickname({ partyId }: { partyId: number }) {
  const { me, activeParty } = useApp();
  const detail = usePartyDetail(partyId);
  if (!activeParty || !detail.data) return null;
  const mine = detail.data.members.find((m) => m.userId === me.id);
  return <PartyNicknameForm key={partyId} party={activeParty} initial={mine?.partyNickname ?? ''} defaultNickname={me.nickname ?? ''} />;
}
