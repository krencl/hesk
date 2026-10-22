import { useMutation, useQuery } from '@tanstack/react-query';
import { useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../api';
import type { BggPreparedGame, BggSearchResult, Game } from '../api/types';
import { useDebounced } from '../lib/useDebounced';
import { Actions } from './Actions';
import { ErrorMessage } from './ErrorMessage';
import { GameListItem } from './GameListItem';

interface Props {
  partyId: number;
  /** Hra vybraná z výsledků je připravená pro formulář. */
  onPick: (game: BggPreparedGame) => void;
  /** Hra z katalogu byla zařazena do party (bez formuláře, hra se nezakládá znovu). */
  onLinked: (game: Game) => void;
  /** Hra se nenašla: založit vlastní s názvem, který uživatel zadal. */
  onCustom: (name: string) => void;
}

export const MIN_QUERY_LENGTH = 2;

/** První krok nové hry: jen pole Název, které hledá hru v partě, v ostatních partách, v cache BGG a na BGG; vlastní hru nabídne až když se nic nehodí. */
export function BggGameSearch({ partyId, onPick, onLinked, onCustom }: Props) {
  const [text, setText] = useState('');
  const query = useDebounced(text.trim());
  const searching = query.length >= MIN_QUERY_LENGTH;
  const search = useQuery({ queryKey: ['bgg-search', partyId, query], queryFn: () => api.bggSearch(partyId, query), enabled: searching });
  const pick = useMutation({ mutationFn: (bggId: number) => api.bggImport(partyId, bggId), onSuccess: onPick });
  const link = useMutation({ mutationFn: (gameId: number) => api.linkGame(partyId, gameId), onSuccess: onLinked });

  const settled = searching && query === text.trim() && !search.isFetching;
  const results = search.data?.results ?? [];

  return (
    <>
      <div className="pad form">
        <label>
          Název
          <input value={text} onChange={(e) => setText(e.target.value)} maxLength={150} autoComplete="off" enterKeyHint="search" autoFocus />
        </label>
        {!searching && <p className="muted hint">Napiš název hry, najdeme ji v databázi, nebo na BoardGameGeek.</p>}
        {searching && search.isFetching && <p className="muted hint">Hledám…</p>}
        <ErrorMessage error={search.error ?? pick.error ?? link.error} />
      </div>
      {searching && results.length > 0 && (
        <ul className="list" aria-label="Nalezené hry">
          {results.map((r) => <Result key={`${r.source}-${r.bggId ?? r.gameId ?? r.name}`} result={r} disabled={pick.isPending || link.isPending} onPick={() => (r.source === 'catalog' && r.gameId ? link.mutate(r.gameId) : r.bggId && pick.mutate(r.bggId))} />)}
        </ul>
      )}
      <div className="pad">
        {settled && results.length === 0 && !search.isError && <p className="muted">Nic jsme nenašli.</p>}
        {settled && search.data?.bgg === 'unavailable' && <p className="muted hint">BoardGameGeek teď není dostupné, ukazuji jen hry, které už známe.</p>}
        {settled && (
          <p className="subtle-action">
            Hra tu není? <button type="button" className="link" onClick={() => onCustom(text.trim())}>Založit vlastní hru</button>
          </p>
        )}
        {settled && search.data?.bgg === 'used' && <p className="muted hint">Data her poskytuje <a href="https://boardgamegeek.com" target="_blank" rel="noreferrer">BoardGameGeek</a>.</p>}
      </div>
      <Actions>
        <Link className="btn outline-secondary" to="/manage/games">Zpět</Link>
      </Actions>
    </>
  );
}

function Result({ result, disabled, onPick }: { result: BggSearchResult; disabled: boolean; onPick: () => void }) {
  if (result.source === 'party' && result.gameId) {
    return (
      <GameListItem to={`/games/${result.gameId}`} imageUrl={result.imageUrl} name={result.name}>
        <span className="secondary">Už v partě</span>
      </GameListItem>
    );
  }
  return (
    <GameListItem imageUrl={result.imageUrl} name={result.year ? `${result.name} (${result.year})` : result.name} disabled={disabled} onClick={onPick}>
      {result.source === 'catalog' && <span className="secondary">Z lokální databáze</span>}
    </GameListItem>
  );
}
