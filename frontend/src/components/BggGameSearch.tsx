import { useMutation, useQuery } from '@tanstack/react-query';
import { useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../api';
import type { BggPreparedGame, BggSearchResult } from '../api/types';
import { useDebounced } from '../lib/useDebounced';
import { Actions } from './Actions';
import { ErrorMessage } from './ErrorMessage';

interface Props {
  partyId: number;
  /** Hra vybraná z výsledků je připravená pro formulář. */
  onPick: (game: BggPreparedGame) => void;
  /** Hra se nenašla: založit vlastní s názvem, který uživatel zadal. */
  onCustom: (name: string) => void;
}

export const MIN_QUERY_LENGTH = 2;

/** První krok nové hry: jen pole Název, které hledá hru v partě, v cache BGG a na BGG; vlastní hru nabídne až když se nic nehodí. */
export function BggGameSearch({ partyId, onPick, onCustom }: Props) {
  const [text, setText] = useState('');
  const query = useDebounced(text.trim());
  const searching = query.length >= MIN_QUERY_LENGTH;
  const search = useQuery({ queryKey: ['bgg-search', partyId, query], queryFn: () => api.bggSearch(partyId, query), enabled: searching });
  const pick = useMutation({ mutationFn: (bggId: number) => api.bggImport(partyId, bggId), onSuccess: onPick });

  const settled = searching && query === text.trim() && !search.isFetching;
  const results = search.data?.results ?? [];

  return (
    <>
      <div className="pad form">
        <label>
          Název
          <input value={text} onChange={(e) => setText(e.target.value)} maxLength={150} autoComplete="off" enterKeyHint="search" autoFocus />
        </label>
        {!searching && <p className="muted hint">Napiš název hry, najdeme ji na BoardGameGeek.</p>}
        {searching && search.isFetching && <p className="muted hint">Hledám…</p>}
        {settled && search.data?.bgg === 'unavailable' && <p className="muted hint">BoardGameGeek teď není dostupné, ukazuji jen hry, které už známe.</p>}
        <ErrorMessage error={search.error ?? pick.error} />
      </div>
      {searching && results.length > 0 && (
        <ul className="list" aria-label="Nalezené hry">
          {results.map((r) => <Result key={`${r.source}-${r.bggId ?? r.gameId}`} result={r} disabled={pick.isPending} onPick={() => r.bggId && pick.mutate(r.bggId)} />)}
        </ul>
      )}
      <div className="pad">
        {settled && results.length === 0 && !search.isError && <p className="muted">Nic jsme nenašli.</p>}
        {settled && (
          <p className="subtle-action">
            Hra tu není? <button type="button" className="link" onClick={() => onCustom(text.trim())}>Založit vlastní hru</button>
          </p>
        )}
        <p className="muted hint">Data her poskytuje <a href="https://boardgamegeek.com" target="_blank" rel="noreferrer">BoardGameGeek</a>.</p>
      </div>
      <Actions>
        <Link className="btn outline-secondary" to="/manage/games">Zpět</Link>
      </Actions>
    </>
  );
}

function Result({ result, disabled, onPick }: { result: BggSearchResult; disabled: boolean; onPick: () => void }) {
  const title = result.year ? `${result.name} (${result.year})` : result.name;
  if (result.source === 'party' && result.gameId) {
    return (
      <li>
        <Link className="list-item" to={`/games/${result.gameId}`}>
          <span className="list-item-body"><span className="h5">{result.name}</span><span className="secondary">Už v partě</span></span>
        </Link>
      </li>
    );
  }
  return (
    <li>
      <button type="button" className="list-item" disabled={disabled} onClick={onPick}>
        <span className="list-item-body"><span className="h5">{title}</span></span>
      </button>
    </li>
  );
}
