<?php

namespace App\Tests\Service\user;

use App\Security\DbUser;
use App\Security\DbUserProvider;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;

/**
 * Tests unitaires pour DbUserProvider.
 *
 * Couvre : chargement depuis users / employees / candidates,
 * gestion des introuvables, rafraîchissement et supportsClass.
 */
#[AllowMockObjectsWithoutExpectations]
class DbUserProviderTest extends TestCase
{
    private Connection&MockObject $connection;
    private DbUserProvider        $provider;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->provider   = new DbUserProvider($this->connection);
    }

    // ─── Données de ligne brutes ──────────────────────────────────────────

    /** @return array<string, mixed> */
    private function rowUser(
        int $id = 1,
        string $username = 'admin',
        string $email = 'admin@hrflow.io',
        string $role = 'ADMIN',
        string $department = 'IT'
    ): array {
        return [
            'id'         => $id,
            'username'   => $username,
            'email'      => $email,
            'password'   => hash('sha256', 'secret'),
            'role'       => $role,
            'department' => $department,
        ];
    }

    /** @return array<string, mixed> */
    private function rowEmployee(int $id = 2): array
    {
        return [
            'id'         => $id,
            'first_name' => 'Paul',
            'last_name'  => 'Martin',
            'age'        => 35,
            'job_title'  => 'Développeur',
            'email'      => 'paul.martin@hrflow.io',
            'password'   => hash('sha256', 'emp_secret'),
            'rh_id'      => 10,
            'department' => 'Tech',
        ];
    }

    /** @return array<string, mixed> */
    private function rowCandidate(int $id = 3): array
    {
        return [
            'id'         => $id,
            'username'   => 'jdoe',
            'email'      => 'jdoe@mail.com',
            'password'   => hash('sha256', 'can_secret'),
            'first_name' => 'Jane',
            'last_name'  => 'Doe',
            'phone'      => '0612345678',
        ];
    }

    // ─── loadUserByIdentifier : table users ──────────────────────────────

    public function testLoadUserByIdentifierRetourneDbUserDepuisUsersTable(): void
    {
        $this->connection->method('fetchAssociative')
            ->willReturnCallback(function (string $sql): array|false {
                if (str_contains($sql, 'FROM users')) {
                    return $this->rowUser();
                }
                return false;
            });

        $user = $this->provider->loadUserByIdentifier('admin');

        $this->assertInstanceOf(DbUser::class, $user);
        $this->assertSame('admin', $user->getUsername());
        $this->assertSame('ADMIN', $user->getRole());
        $this->assertSame('users', $user->getSource());
    }

    public function testLoadUserByIdentifierHydrateEmailEtDepartementDuUser(): void
    {
        $this->connection->method('fetchAssociative')
            ->willReturnCallback(function (string $sql): array|false {
                if (str_contains($sql, 'FROM users')) {
                    return $this->rowUser(email: 'rh@hrflow.io', department: 'RH', role: 'RH');
                }
                return false;
            });

        $user = $this->provider->loadUserByIdentifier('rh@hrflow.io');

        $this->assertSame('rh@hrflow.io', $user->getEmail());
        $this->assertSame('RH', $user->getDepartment());
    }

    // ─── loadUserByIdentifier : table employees ───────────────────────────

    public function testLoadUserByIdentifierRetourneDbUserDepuisEmployeesTable(): void
    {
        $this->connection->method('fetchAssociative')
            ->willReturnCallback(function (string $sql): array|false {
                if (str_contains($sql, 'FROM users')) {
                    return false;
                }
                if (str_contains($sql, 'FROM employees')) {
                    return $this->rowEmployee();
                }
                return false;
            });

        $user = $this->provider->loadUserByIdentifier('paul.martin@hrflow.io');

        $this->assertInstanceOf(DbUser::class, $user);
        $this->assertSame('employees', $user->getSource());
        $this->assertSame('Paul', $user->getFirstName());
        $this->assertSame('Martin', $user->getLastName());
        $this->assertSame('Paul Martin', $user->getFullName());
        $this->assertSame(['ROLE_EMPLOYEE'], $user->getRoles());
    }

    public function testLoadUserByIdentifierEmployeHydrateRhIdEtAge(): void
    {
        $this->connection->method('fetchAssociative')
            ->willReturnCallback(function (string $sql): array|false {
                if (str_contains($sql, 'FROM employees')) {
                    return $this->rowEmployee(id: 7);
                }
                return false;
            });

        $user = $this->provider->loadUserByIdentifier('paul.martin@hrflow.io');

        $this->assertSame(7, $user->getId());
        $this->assertSame(10, $user->getRhId());
        $this->assertSame(35, $user->getAge());
    }

    // ─── loadUserByIdentifier : table candidates ──────────────────────────

    public function testLoadUserByIdentifierRetourneDbUserDepuisCandidatesTable(): void
    {
        $this->connection->method('fetchAssociative')
            ->willReturnCallback(function (string $sql): array|false {
                if (str_contains($sql, 'FROM users') || str_contains($sql, 'FROM employees')) {
                    return false;
                }
                if (str_contains($sql, 'FROM candidates')) {
                    return $this->rowCandidate();
                }
                return false;
            });

        $user = $this->provider->loadUserByIdentifier('jdoe');

        $this->assertInstanceOf(DbUser::class, $user);
        $this->assertSame('candidates', $user->getSource());
        $this->assertSame('jdoe', $user->getUsername());
        $this->assertSame(['ROLE_CANDIDATE'], $user->getRoles());
    }

    // ─── loadUserByIdentifier : introuvable ──────────────────────────────

    public function testLoadUserByIdentifierLeveUserNotFoundExceptionSiInconnu(): void
    {
        $this->connection->method('fetchAssociative')->willReturn(false);

        $this->expectException(UserNotFoundException::class);
        $this->provider->loadUserByIdentifier('inconnu');
    }

    // ─── refreshUser ─────────────────────────────────────────────────────

    public function testRefreshUserRechargeLUserDepuisUsersTable(): void
    {
        $dbUser = new DbUser(1, 'admin', 'admin@hrflow.io', 'hash', 'ADMIN', 'users');

        $this->connection->method('fetchAssociative')
            ->willReturnCallback(function (string $sql) use (&$dbUser): array|false {
                if (str_contains($sql, 'FROM users WHERE id')) {
                    return $this->rowUser(id: 1);
                }
                return false;
            });

        $refreshed = $this->provider->refreshUser($dbUser);

        $this->assertInstanceOf(DbUser::class, $refreshed);
        $this->assertSame(1, $refreshed->getId());
    }

    public function testRefreshUserRechargeLEmployeDepuisEmployeesTable(): void
    {
        $dbUser = new DbUser(2, 'Paul Martin', 'paul@hrflow.io', 'hash', 'EMPLOYEE', 'employees');

        $this->connection->method('fetchAssociative')
            ->willReturnCallback(function (string $sql): array|false {
                if (str_contains($sql, 'FROM employees WHERE id')) {
                    return $this->rowEmployee(id: 2);
                }
                return false;
            });

        $refreshed = $this->provider->refreshUser($dbUser);

        $this->assertSame('employees', $refreshed->getSource());
        $this->assertSame(2, $refreshed->getId());
    }

    public function testRefreshUserRechargeLeCandidatDepuisCandidatesTable(): void
    {
        $dbUser = new DbUser(3, 'jdoe', 'jdoe@mail.com', 'hash', 'CANDIDATE', 'candidates');

        $this->connection->method('fetchAssociative')
            ->willReturnCallback(function (string $sql): array|false {
                if (str_contains($sql, 'FROM candidates WHERE id')) {
                    return $this->rowCandidate(id: 3);
                }
                return false;
            });

        $refreshed = $this->provider->refreshUser($dbUser);

        $this->assertSame('candidates', $refreshed->getSource());
        $this->assertSame(3, $refreshed->getId());
    }

    public function testRefreshUserLeveExceptionSiUserIntrouvable(): void
    {
        $dbUser = new DbUser(999, 'ghost', null, 'hash', 'ADMIN', 'users');
        $this->connection->method('fetchAssociative')->willReturn(false);

        $this->expectException(UserNotFoundException::class);
        $this->provider->refreshUser($dbUser);
    }

    public function testRefreshUserLeveExceptionSiEmployeIntrouvable(): void
    {
        $dbUser = new DbUser(999, 'ghost', null, 'hash', 'EMPLOYEE', 'employees');
        $this->connection->method('fetchAssociative')->willReturn(false);

        $this->expectException(UserNotFoundException::class);
        $this->provider->refreshUser($dbUser);
    }

    public function testRefreshUserLeveExceptionSiCandidatIntrouvable(): void
    {
        $dbUser = new DbUser(999, 'ghost', null, 'hash', 'CANDIDATE', 'candidates');
        $this->connection->method('fetchAssociative')->willReturn(false);

        $this->expectException(UserNotFoundException::class);
        $this->provider->refreshUser($dbUser);
    }

    public function testRefreshUserLeveExceptionSiClasseNonSupportee(): void
    {
        $this->expectException(UnsupportedUserException::class);

        // Passer un UserInterface qui n'est pas un DbUser
        $fakeUser = new class implements \Symfony\Component\Security\Core\User\UserInterface {
            public function getRoles(): array { return []; }
            public function getPassword(): ?string { return null; }
            public function getSalt(): ?string { return null; }
            public function getUsername(): string { return 'fake'; }
            public function getUserIdentifier(): string { return 'fake'; }
            public function eraseCredentials(): void {}
        };

        $this->provider->refreshUser($fakeUser);
    }

    // ─── supportsClass ────────────────────────────────────────────────────

    public function testSupportsClassRetourneTruePourDbUser(): void
    {
        $this->assertTrue($this->provider->supportsClass(DbUser::class));
    }

    public function testSupportsClassRetourneFalsePourUneAutreClasse(): void
    {
        $this->assertFalse($this->provider->supportsClass(\stdClass::class));
    }
}
