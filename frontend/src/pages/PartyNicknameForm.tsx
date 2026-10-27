import { useMutation, useQueryClient } from '@tanstack/react-query';
import { type FormEvent, useState } from 'react';
import { api } from '../api';
import type { Party } from '../api/types';
import { Actions } from '../components/Actions';
import { ErrorMessage } from '../components/ErrorMessage';

/** Nick přihlášeného člena jen v dané partě; prázdné pole = použije se defaultní přezdívka. */
export function PartyNicknameForm({ party, initial, defaultNickname }: { party: Party; initial: string; defaultNickname: string }) {
  const queryClient = useQueryClient();
  const [nickname, setNickname] = useState(initial);
  const save = useMutation({
    mutationFn: () => api.setPartyNickname(party.id, nickname.trim()),
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
        Nick v partě {party.name}
        <input value={nickname} onChange={(e) => setNickname(e.target.value)} placeholder={defaultNickname} minLength={2} maxLength={100} />
        <small className="muted">Pokud je pole prázdné, použije se defaultní nick.</small>
      </label>
      <ErrorMessage error={save.error} />
      {save.isSuccess && <div className="success">Uloženo.</div>}
      <Actions><button className="btn" type="submit" disabled={save.isPending}>Uložit</button></Actions>
    </form>
  );
}
