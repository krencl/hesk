import { useMutation } from '@tanstack/react-query';
import { type FormEvent, useState } from 'react';
import { Navigate, useParams } from 'react-router-dom';
import { api } from '../api';
import type { PartyDetail } from '../api/types';
import { Actions } from '../components/Actions';
import { ErrorMessage } from '../components/ErrorMessage';
import { LoadingOrError, PartyNotFound, isNotFound } from '../components/PageState';
import { PartyHeader } from '../components/PartyHeader';
import { toLocalInput } from '../lib/datetime';
import { downloadJson } from '../lib/download';
import { type ExportScope, exportParams, isExportRangeValid } from '../lib/partyExport';
import { usePartyDetail } from '../lib/usePartyDetail';

/** Data party (jen admin): export do JSON, celý nebo omezený na rozsah dat či sezení. */
export function PartyDataPage() {
  const id = Number(useParams().id);
  const detail = usePartyDetail(id);

  if (isNotFound(detail.error)) return <PartyNotFound />;
  if (!detail.data) return <LoadingOrError error={detail.error} rows={2} withImage={false} />;
  if (detail.data.role !== 'admin') return <Navigate to={`/parties/${id}`} replace />;

  return <PartyDataForm party={detail.data} />;
}

function PartyDataForm({ party }: { party: PartyDetail }) {
  const [scope, setScope] = useState<ExportScope>('all');
  const [from, setFrom] = useState('');
  const [to, setTo] = useState('');
  const range = { scope, from, to };

  const exportData = useMutation({
    mutationFn: () => api.exportParty(party.id, exportParams(range)),
    onSuccess: (data) => downloadJson(`hesk-export-${toLocalInput(new Date()).slice(0, 10)}.json`, data),
  });
  const submit = (e: FormEvent) => {
    e.preventDefault();
    exportData.mutate();
  };
  const changeScope = (next: ExportScope) => {
    setScope(next);
    setFrom('');
    setTo('');
  };

  return (
    <section className="page">
      <PartyHeader party={party} />
      <form className="pad form" onSubmit={submit}>
        <p className="muted">
          Export do souboru JSON: hráči, hry (rozšíření, postavy, tagy), sezení a zápisy. Entity se v souboru odkazují pomocí ID, žádné e-maily ani údaje o účtech v něm nejsou.
        </p>
        <label>
          Rozsah exportu
          <select value={scope} onChange={(e) => changeScope(e.target.value as ExportScope)}>
            <option value="all">Všechna data</option>
            <option value="dates">Zápisy podle data</option>
            <option value="sessions">Zápisy podle sezení</option>
          </select>
        </label>
        {scope === 'dates' && (
          <>
            <label>Od<input type="date" value={from} max={to || undefined} onChange={(e) => setFrom(e.target.value)} /></label>
            <label>Do (včetně)<input type="date" value={to} min={from || undefined} onChange={(e) => setTo(e.target.value)} /></label>
          </>
        )}
        {scope === 'sessions' && (
          <>
            <label>Od sezení<input type="number" inputMode="numeric" min={1} step={1} value={from} onChange={(e) => setFrom(e.target.value)} /></label>
            <label>Do sezení (včetně)<input type="number" inputMode="numeric" min={1} step={1} value={to} onChange={(e) => setTo(e.target.value)} /></label>
          </>
        )}
        {scope !== 'all' && (
          <small className="muted">Prázdné pole znamená bez omezení z dané strany. Filtr omezuje jen sezení a zápisy, hráči, hry a tagy se exportují vždy.</small>
        )}
        <ErrorMessage error={exportData.error} />
        <Actions>
          <button className="btn" type="submit" disabled={exportData.isPending || !isExportRangeValid(range)}>Exportovat data</button>
        </Actions>
      </form>
    </section>
  );
}
