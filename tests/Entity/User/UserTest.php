<?php

namespace App\Tests\Entity\User;

use App\Entity\User\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(User::class)]
final class UserTest extends TestCase
{
    public function testInitialState(): void
    {
        $user = new User();

        $this->assertNull($user->getId());
        $this->assertNull($user->getEmail());
        $this->assertNull($user->getPassword());
        $this->assertNull($user->getPseudo());
        $this->assertNull($user->getSlug());
        $this->assertNull($user->getCreatedAt());
        $this->assertNull($user->getUpdatedAt());
        $this->assertNull($user->getLastLoginAt());

        $roles = $user->getRoles();
        $this->assertContains('ROLE_USER', $roles);

        $this->assertSame(
            count($roles),
            count(array_unique($roles)),
            'Les rôles contiennent des doublons: '.var_export($roles, true)
        );
    }

    public function testSettersAndGetters(): void
    {
        $user = new User();

        $email = 'nicolas@example.test';
        $password = 'hash$argon2id$whatever';
        $pseudo = 'Nico';
        $slug = 'nicolas-example-test';
        $created = new \DateTimeImmutable('2025-01-01 10:00:00');
        $updated = new \DateTimeImmutable('2025-01-02 11:00:00');
        $lastLogin = new \DateTimeImmutable('2025-01-03 12:00:00');

        $user
            ->setEmail($email)
            ->setPassword($password)
            ->setPseudo($pseudo)
            ->setSlug($slug)
            ->setCreatedAt($created)
            ->setUpdatedAt($updated)
            ->setLastLoginAt($lastLogin)
            ->setRoles(['ROLE_ADMIN', 'ROLE_EDITOR']);

        $this->assertSame($email, $user->getEmail());
        $this->assertSame($password, $user->getPassword());
        $this->assertSame($pseudo, $user->getPseudo());
        $this->assertSame($slug, $user->getSlug());
        $this->assertSame($created, $user->getCreatedAt());
        $this->assertSame($updated, $user->getUpdatedAt());
        $this->assertSame($lastLogin, $user->getLastLoginAt());

        $roles = $user->getRoles();
        $this->assertContains('ROLE_USER', $roles);
        $this->assertContains('ROLE_ADMIN', $roles);
        $this->assertContains('ROLE_EDITOR', $roles);
    }

    public function testGetUserIdentifierReturnsEmail(): void
    {
        $user = new User();
        $user->setEmail('id@example.test');

        $this->assertSame('id@example.test', $user->getUserIdentifier());
    }

    public function testGetRolesAlwaysKeepsRoleUserAndIsUnique(): void
    {
        $user = new User();
        $user->setRoles(['ROLE_USER', 'ROLE_USER', 'ROLE_ADMIN']);

        $roles = $user->getRoles();

        $this->assertContains('ROLE_USER', $roles);
        $this->assertContains('ROLE_ADMIN', $roles);

        $this->assertSame(
            count($roles),
            count(array_unique($roles)),
            'Les rôles contiennent des doublons: '.var_export($roles, true)
        );
    }

    public function testSerializeHashesPasswordWithCrc32c(): void
    {
        $user = new User();
        $user->setPassword('super-secret');

        $serialized = $user->__serialize();

        $privatePasswordKey = "\0".User::class."\0password";

        $this->assertArrayHasKey($privatePasswordKey, $serialized);

        $expectedHash = hash('crc32c', 'super-secret');

        $this->assertSame($expectedHash, $serialized[$privatePasswordKey]);
        $this->assertNotSame(
            'super-secret',
            $serialized[$privatePasswordKey],
            'Le mot de passe ne doit pas apparaître en clair.'
        );
    }

    public function testEraseCredentialsIsNoop(): void
    {
        $user = new User();
        $user->eraseCredentials();
        $this->assertTrue(true);
    }
}
