<?php

declare(strict_types=1);

namespace App\Domain;

use App\Domain\Exception\DomainException;

/**
 * Which plays a party export contains (ADR 0033): everything, plays started in a date-time range
 * (`from` inclusive, `to` exclusive), or plays of sessions in a number range (both ends inclusive).
 * Either end of a range may be open; a date range and a session range cannot be combined.
 */
final readonly class PartyExportFilter
{
    public function __construct(
        public ?\DateTimeImmutable $from = null,
        public ?\DateTimeImmutable $to = null,
        public ?int $fromSession = null,
        public ?int $toSession = null,
    ) {
        if ($this->isDateRange() && $this->isSessionRange()) {
            throw new DomainException('Vyber buď rozsah dat, nebo rozsah sezení.');
        }
        if (null !== $from && null !== $to && $from >= $to) {
            throw new DomainException('Začátek rozsahu dat musí být před jeho koncem.');
        }
        if (null !== $fromSession && null !== $toSession && $fromSession > $toSession) {
            throw new DomainException('První sezení rozsahu nesmí být po posledním.');
        }
    }

    /** @param array<string, mixed> $query `from`, `to` (ISO 8601 date-times), `fromSession`, `toSession` (session numbers); empty values are ignored */
    public static function fromQuery(array $query): self
    {
        return new self(
            self::dateTime($query['from'] ?? null),
            self::dateTime($query['to'] ?? null),
            self::sessionNumber($query['fromSession'] ?? null),
            self::sessionNumber($query['toSession'] ?? null),
        );
    }

    public function isDateRange(): bool
    {
        return null !== $this->from || null !== $this->to;
    }

    public function isSessionRange(): bool
    {
        return null !== $this->fromSession || null !== $this->toSession;
    }

    /** @return array{type: string, from?: string|int|null, to?: string|int|null} the filter as written to the export's `meta` */
    public function toArray(): array
    {
        if ($this->isDateRange()) {
            return ['type' => 'date', 'from' => $this->from?->format(\DATE_ATOM), 'to' => $this->to?->format(\DATE_ATOM)];
        }
        if ($this->isSessionRange()) {
            return ['type' => 'session', 'from' => $this->fromSession, 'to' => $this->toSession];
        }

        return ['type' => 'all'];
    }

    private static function dateTime(mixed $value): ?\DateTimeImmutable
    {
        if (null === $value || '' === $value) {
            return null;
        }
        try {
            return \is_string($value) ? new \DateTimeImmutable($value) : throw new \InvalidArgumentException();
        } catch (\Exception) {
            throw new DomainException('Neplatné datum a čas.');
        }
    }

    private static function sessionNumber(mixed $value): ?int
    {
        if (null === $value || '' === $value) {
            return null;
        }
        if (!\is_string($value) || !ctype_digit($value) || \strlen($value) > 9) {
            throw new DomainException('Neplatné číslo sezení.');
        }

        return (int) $value;
    }
}
