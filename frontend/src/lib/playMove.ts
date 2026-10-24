import type { MovePreviewPlayer } from '../api/types';

/** Co se při přesunu zápisu stane s hráčem (text pod jménem v náhledu). */
export function describeMove(p: Pick<MovePreviewPlayer, 'fromMember' | 'toMember' | 'created'>): string {
  if (p.fromMember) {
    if (p.toMember) return 'člen beze změny';
    return p.created ? 'z člena na nového hosta' : 'z člena na existujícího hosta';
  }
  if (p.toMember) return 'z hosta na člena';
  return p.created ? 'nový host' : 'host beze změny';
}
