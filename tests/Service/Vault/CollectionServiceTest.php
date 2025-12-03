<?php

namespace App\Tests\Service\Vault;

use App\Entity\User\User;
use App\Entity\Vault\Catalog\Artist;
use App\Entity\Vault\Catalog\Record;
use App\Entity\Vault\Collection\Edition;
use App\Service\Vault\CollectionService;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CollectionServiceTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private CollectionService $service;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->service = self::getContainer()->get(CollectionService::class);

        (new ORMPurger($this->em))->purge();
    }

    public function testGetCollectionReturnsOnlyOwnersEditionsSorted(): void
    {
        $u1 = $this->createUser('alice@example.test', 'Alice');
        $u2 = $this->createUser('bob@example.test', 'Bob');

        $pinkFloyd = $this->createArtist('Pink Floyd', 'GB', 'United Kingdom');
        $beatles = $this->createArtist('The Beatles', 'GB', 'United Kingdom');
        $abba = $this->createArtist('ABBA', 'SE', 'Sweden');

        $this->createEdition($u1, $this->createRecord($abba, 'Arrival', 1976));
        $this->createEdition($u1, $this->createRecord($pinkFloyd, 'The Wall', 1979));
        $this->createEdition($u1, $this->createRecord($beatles, 'Abbey Road', 1969));

        $this->createEdition($u2, $this->createRecord($beatles, 'Let It Be', 1970));

        $list = $this->service->getCollection($u1);

        $this->assertIsArray($list);
        $this->assertCount(3, $list);
        $this->assertContainsOnlyInstancesOf(Edition::class, $list);

        $orderedTitles = array_map(fn(Edition $e) => $e->getRecord()->getTitle(), $list);
        $this->assertSame(['Arrival', 'The Wall', 'Abbey Road'], $orderedTitles);
    }

    public function testGetCollectionPagePaginatesAndBounds(): void
    {
        $u = $this->createUser('carol@example.test', 'Carol');

        $artist = $this->createArtist('Various', 'XX', 'Unknown');
        for ($i = 1; $i <= 14; $i++) {
            $this->createEdition($u, $this->createRecord($artist, sprintf('Record %02d', $i), 1970 + $i));
        }

        $p1 = $this->service->getCollectionPage($u, 1, 6);
        $this->assertSame(14, $p1['total']);
        $this->assertSame(3, $p1['pages']);
        $this->assertSame(1, $p1['page']);
        $this->assertCount(6, $p1['items']);

        $p3 = $this->service->getCollectionPage($u, 3, 6);
        $this->assertSame(3, $p3['page']);
        $this->assertCount(2, $p3['items']);

        $p99 = $this->service->getCollectionPage($u, 99, 6);
        $this->assertSame(3, $p99['page']);
        $this->assertCount(2, $p99['items']);

        $p0 = $this->service->getCollectionPage($u, 0, 6);
        $this->assertSame(1, $p0['page']);
        $this->assertCount(6, $p0['items']);
    }

    private function createUser(string $email, string $pseudo): User
    {
        $u = (new User())
            ->setEmail($email)
            ->setPseudo($pseudo)
            ->setPassword('irrelevant-here');
        $this->em->persist($u);
        $this->em->flush();

        return $u;
    }

    private function createArtist(string $name, string $countryCode = 'XX', ?string $countryName = null): Artist
    {
        $a = new Artist();

        if (method_exists($a, 'setName')) {
            $a->setName($name);
        }
        if (method_exists($a, 'setNameCanonical')) {
            $a->setNameCanonical(mb_strtolower(trim($name), 'UTF-8'));
        }
        if (method_exists($a, 'setSlug')) {
            $a->setSlug(preg_replace('~\s+~', '-', mb_strtolower(trim($name), 'UTF-8')));
        }
        if (method_exists($a, 'setCountryCode')) {
            $a->setCountryCode($countryCode); // NOT NULL
        }
        if (method_exists($a, 'setCountryName') && $countryName !== null) {
            $a->setCountryName($countryName);
        }

        $this->em->persist($a);
        $this->em->flush();

        return $a;
    }

    private function createRecord(Artist $artist, string $title, int $year): Record
    {
        $r = new Record();
        if (method_exists($r, 'setTitle')) {
            $r->setTitle($title);
        }
        if (method_exists($r, 'setTitleCanonical')) {
            $r->setTitleCanonical(mb_strtolower(trim($title), 'UTF-8'));
        }
        if (method_exists($r, 'setYearOriginal')) {
            $r->setYearOriginal($year);
        }
        if (method_exists($r, 'setArtist')) {
            $r->setArtist($artist);
        }
        $this->em->persist($r);
        $this->em->flush();

        return $r;
    }

    private function createEdition(User $owner, Record $record): Edition
    {
        $e = new Edition();
        if (method_exists($e, 'setOwner')) {
            $e->setOwner($owner);
        }
        if (method_exists($e, 'setRecord')) {
            $e->setRecord($record);
        }
        $this->em->persist($e);
        $this->em->flush();

        return $e;
    }
}
