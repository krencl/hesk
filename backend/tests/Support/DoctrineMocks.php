<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;

/** Mocky Doctrine pro testy: nic nesahá do databáze, `find` vrací jen předem nastavené entity. */
trait DoctrineMocks
{
    /**
     * @param list<object> $known entity, které `find(Třída, id)` najde (podle `getId()`)
     *
     * `persist` nově uloženým entitám přidělí ID, `wrapInTransaction` jen zavolá callback.
     * Zavolané persist/remove/flush jsou k dispozici v `$calls`.
     */
    protected function mockEm(array $known = [], ?\ArrayObject $calls = null): EntityManagerInterface
    {
        $calls ??= new \ArrayObject();
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('find')->willReturnCallback(static function (string $class, mixed $id) use ($known): ?object {
            foreach ($known as $entity) {
                if ($entity instanceof $class && $entity->getId() === $id) {
                    return $entity;
                }
            }

            return null;
        });
        $em->method('persist')->willReturnCallback(static function (object $entity) use ($calls): void {
            $calls['persist'][] = $entity;
            if (method_exists($entity, 'getId') && null === $entity->getId() && property_exists($entity, 'id')) {
                Fixtures::id($entity);
            }
        });
        $em->method('remove')->willReturnCallback(static function (object $entity) use ($calls): void {
            $calls['remove'][] = $entity;
        });
        $em->method('flush')->willReturnCallback(static function () use ($calls): void {
            $calls['flush'] = ($calls['flush'] ?? 0) + 1;
        });
        $em->method('wrapInTransaction')->willReturnCallback(static fn (callable $fn): mixed => $fn());

        return $em;
    }

    /**
     * Dotaz (DQL/QueryBuilder), který nic nespouští a vrací dané výsledky.
     *
     * @param array<string, mixed> $results `getResult`, `getArrayResult`, `getSingleScalarResult`, `getOneOrNullResult`, `execute`
     */
    protected function mockQuery(array $results): Query
    {
        $query = $this->createStub(Query::class);
        foreach (['setParameter', 'setParameters', 'setMaxResults'] as $fluent) {
            $query->method($fluent)->willReturnSelf();
        }
        foreach ($results as $method => $value) {
            $query->method($method)->willReturn($value);
        }

        return $query;
    }
}
