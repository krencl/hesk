import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useApp } from '../AppContext';
import { api } from '../api';
import { ApiError } from '../api/client';
import type { PartyRole } from '../api/types';
import { Actions } from '../components/Actions';
import { ErrorMessage } from '../components/ErrorMessage';
import { ConfirmPopup } from '../components/ConfirmPopup';
import { Icon } from '../components/Icon';
import { PartyHeader } from '../components/PartyHeader';
import { SelectPopup } from '../components/SelectPopup';
import { LEAVE_PARTY_MANAGEMENT_PATH } from '../lib/partyPath';
import { ROLE_LABELS, ROLE_ORDER } from '../lib/roles';
import { ListSkeleton } from '../components/Skeleton';
import { qk } from '../api/queryKeys';

/** Detail party: členové a jejich role, pozvánky a opuštění party. Seznam part a zakládání jsou ve správě part. */
export function PartyDetailPage() {
  const id = Number(useParams().id);
  const { me } = useApp();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const [inviteLink, setInviteLink] = useState<string | null>(null);
  // člen, kterého se právě potvrzuje odebrání (nebo já při opuštění party)
  const [confirming, setConfirming] = useState<{ userId: number; nickname: string } | null>(null);
  const detail = useQuery({ queryKey: qk.party(id), queryFn: () => api.party(id) });
  const refresh = () => Promise.all([queryClient.invalidateQueries({ queryKey: qk.parties() }), queryClient.invalidateQueries({ queryKey: qk.party() })]);

  const invite = useMutation({
    mutationFn: () => api.invite(id),
    onSuccess: (r) => setInviteLink(`${window.location.origin}/join/${r.token}`),
  });
  const changeRole = useMutation({ mutationFn: (v: { userId: number; role: PartyRole }) => api.changeRole(id, v.userId, v.role), onSuccess: refresh });
  const remove = useMutation({
    mutationFn: (userId: number) => api.removeMember(id, userId),
    onSuccess: async (_, userId) => {
      await refresh();
      if (userId === me.id) navigate('/parties', { replace: true });
    },
  });

  if (detail.error instanceof ApiError && (detail.error.status === 404 || detail.error.status === 403)) {
    return (
      <section className="page pad">
        <p className="empty">Tahle parta neexistuje nebo v ní nejsi.</p>
        <Actions><Link className="btn outline-secondary" to="/parties">Správa part</Link></Actions>
      </section>
    );
  }
  if (!detail.data) return detail.error ? <section className="page pad"><ErrorMessage inline error={detail.error} /></section> : <ListSkeleton rows={3} withImage={false} />;

  const party = detail.data;
  const isAdmin = party.role === 'admin';

  return (
    <section className="page party-detail">
      <PartyHeader party={party} />
      <div className="pad">
        <ErrorMessage error={changeRole.error ?? remove.error} />
      </div>
      <ul className="list">
        {party.members.map((m) => (
          <li key={m.userId} className="member">
            <span className="member-name">
              {m.nickname}
              {!isAdmin && <small className="muted">{ROLE_LABELS[m.role]}</small>}
            </span>
            {isAdmin && (
              <span className="member-actions">
                <SelectPopup
                  title={`Role – ${m.nickname}`}
                  value={m.role}
                  options={ROLE_ORDER.map((r) => ({ value: r, label: ROLE_LABELS[r] }))}
                  onChange={(role) => role !== m.role && changeRole.mutate({ userId: m.userId, role })}
                  searchable={false}
                  className="role-select"
                />
                {m.userId !== me.id && (
                  <button
                    type="button"
                    className="icon-button danger"
                    aria-label={`Odebrat ${m.nickname}`}
                    onClick={() => setConfirming({ userId: m.userId, nickname: m.nickname ?? 'člen' })}
                  >
                    <Icon name="delete" />
                  </button>
                )}
              </span>
            )}
            {!isAdmin && m.userId === me.id && (
              <button type="button" className="link danger" onClick={() => setConfirming({ userId: m.userId, nickname: m.nickname ?? 'člen' })}>Opustit</button>
            )}
          </li>
        ))}
      </ul>

      <div className="pad">
        <ErrorMessage error={invite.error} />
        {inviteLink && (
          <p className="invite">
            Pošli tento odkaz (platí 7 dní):<br />
            <input readOnly value={inviteLink} onFocus={(e) => e.currentTarget.select()} aria-label="Odkaz pozvánky" />
          </p>
        )}
      </div>
      {confirming && (
        <ConfirmPopup
          title={confirming.userId === me.id ? 'Opustit partu?' : `Odebrat člena ${confirming.nickname}?`}
          confirmLabel={confirming.userId === me.id ? 'Opustit' : 'Odebrat'}
          destructive
          onCancel={() => setConfirming(null)}
          onConfirm={() => {
            remove.mutate(confirming.userId);
            setConfirming(null);
          }}
        >
          {confirming.userId === me.id ? (
            <>
              <p>Ztratíš přístup do party {party.name}. Tvoje zápisy a statistiky v ní zůstanou, ale z tebe se stane host (bez účtu).</p>
              <p>Zpátky se dostaneš jen přes novou pozvánku.</p>
            </>
          ) : (
            <>
              <p>{confirming.nickname} přijde o přístup do party. Z člena se stane host, takže jeho zápisy a statistiky zůstanou a půjde ho dál zapisovat do her.</p>
              <p>Zpátky se dostane jen přes novou pozvánku.</p>
            </>
          )}
        </ConfirmPopup>
      )}
      <Actions>
        {isAdmin && <button type="button" className="btn" onClick={() => invite.mutate()}>Pozvat hráče</button>}
        <Link className="btn outline-secondary" to={LEAVE_PARTY_MANAGEMENT_PATH}>Zrušit</Link>
      </Actions>
    </section>
  );
}
