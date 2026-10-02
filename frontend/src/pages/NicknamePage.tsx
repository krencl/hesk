import { useMutation, useQueryClient } from '@tanstack/react-query';
import { type FormEvent, useState } from 'react';
import { api } from '../api';
import { ErrorMessage } from '../components/ErrorMessage';

/** Formulář přezdívky – povinný krok po prvním přihlášení, jinak úprava v profilu. */
export function NicknamePage({ first = false, initial = '' }: { first?: boolean; initial?: string }) {
  const queryClient = useQueryClient();
  const [nickname, setNickname] = useState(initial);
  const save = useMutation({
    mutationFn: () => api.setNickname(nickname.trim()),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['me'] }),
  });
  const submit = (e: FormEvent) => {
    e.preventDefault();
    save.mutate();
  };

  return (
    <form className={first ? 'login' : 'form'} onSubmit={submit}>
      {first && <h1>Jak ti máme říkat?</h1>}
      <label>
        Přezdívka
        <input value={nickname} onChange={(e) => setNickname(e.target.value)} minLength={2} maxLength={100} required autoFocus={first} />
      </label>
      <ErrorMessage error={save.error} />
      {save.isSuccess && !first && <div className="success">Uloženo.</div>}
      <button className="btn" type="submit" disabled={save.isPending}>Uložit</button>
    </form>
  );
}
