import { useMutation, useQueryClient } from '@tanstack/react-query';
import { type FormEvent, useState } from 'react';
import { useApp } from '../AppContext';
import { api } from '../api';
import type { Party } from '../api/types';
import { Actions } from '../components/Actions';
import { ErrorMessage } from '../components/ErrorMessage';
import { PageTitle } from '../components/PageTitle';
import { usePartyDetail } from '../lib/usePartyDetail';

export function SettingsPage() {
  const { me, activeParty } = useApp();
  return (
    <section className="page">
      <PageTitle>Nastavení</PageTitle>
      <div className="pad">
        <p className="muted">{me.email}</p>
        {activeParty ? <WithParty party={activeParty} /> : <NicknamesForm />}
      </div>
    </section>
  );
}

/** Formulář se začne vykreslovat, až když je znám aktuální nick v partě (detail party). */
function WithParty({ party }: { party: Party }) {
  const { me } = useApp();
  const detail = usePartyDetail(party.id);
  if (!detail.data) return null;
  const mine = detail.data.members.find((m) => m.userId === me.id);
  return <NicknamesForm key={party.id} party={party} initialPartyNickname={mine?.partyNickname ?? ''} />;
}

/** Defaultní přezdívka účtu a (je-li aktivní parta) nick jen v této partě; jedno tlačítko ukládá obojí. */
function NicknamesForm({ party, initialPartyNickname = '' }: { party?: Party; initialPartyNickname?: string }) {
  const { me } = useApp();
  const queryClient = useQueryClient();
  const initialNickname = me.nickname ?? '';
  const [nickname, setNickname] = useState(initialNickname);
  const [partyNickname, setPartyNickname] = useState(initialPartyNickname);
  const save = useMutation({
    mutationFn: async () => {
      // nejdřív defaultní nick, pak nick v partě (ten ho případně přebije); ukládá se jen, co se změnilo
      if (nickname.trim() !== initialNickname) await api.setNickname(nickname.trim());
      if (party && partyNickname.trim() !== initialPartyNickname) await api.setPartyNickname(party.id, partyNickname.trim());
    },
    // jméno hráče se mění všude (zápisy, statistiky, členové), proto se obnoví všechno
    onSuccess: () => queryClient.invalidateQueries(),
  });
  const submit = (e: FormEvent) => {
    e.preventDefault();
    save.mutate();
  };

  return (
    <form className="form" onSubmit={submit}>
      <label>
        Přezdívka
        <input value={nickname} onChange={(e) => setNickname(e.target.value)} minLength={2} maxLength={100} required />
        <small className="muted">Defaultní nick, použije se pro nové party</small>
      </label>
      {party && (
        <label>
          Nick v partě {party.name}
          <input value={partyNickname} onChange={(e) => setPartyNickname(e.target.value)} placeholder={nickname.trim() || initialNickname} minLength={2} maxLength={100} />
          <small className="muted">Pokud je pole prázdné, použije se defaultní nick.</small>
        </label>
      )}
      <ErrorMessage error={save.error} />
      {save.isSuccess && <div className="success">Uloženo.</div>}
      <Actions><button className="btn" type="submit" disabled={save.isPending}>Uložit</button></Actions>
    </form>
  );
}
