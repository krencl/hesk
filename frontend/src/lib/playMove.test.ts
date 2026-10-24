import { describe, expect, it } from 'vitest';
import { describeMove } from './playMove';

describe('describeMove', () => {
  it.each([
    [{ fromMember: true, toMember: true, created: false }, 'člen beze změny'],
    [{ fromMember: true, toMember: false, created: true }, 'z člena na nového hosta'],
    [{ fromMember: true, toMember: false, created: false }, 'z člena na hosta (host tohoto jména už v partě je)'],
    [{ fromMember: false, toMember: false, created: false }, 'host beze změny'],
    [{ fromMember: false, toMember: false, created: true }, 'nový host'],
  ])('%j → %s', (player, text) => expect(describeMove(player)).toBe(text));
});
