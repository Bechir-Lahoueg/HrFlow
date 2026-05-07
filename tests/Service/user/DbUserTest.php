<?php

namespace App\Tests\Service\user;

use App\Security\DbUser;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour l'objet de valeur DbUser.
 *
 * Couvre : mappage des rôles, détection source employé,
 * nom complet, identifiant Symfony et accesseurs de base.
 */
class DbUserTest extends TestCase
{
    // ─────────────── Fabrique ────────────────────────────────────────────

    private function buildUser(
        string $role,
        string $source = 'users',
        ?string $firstName = null,
        ?string $lastName = null,
        string $username = 'jdupont',
        string $password = 'hashed',
        ?string $email = 'jdupont@example.com',
    ): DbUser {
        return new DbUser(
            id: 1,
            username: $username,
            email: $email,
            password: $password,
            role: $role,
            source: $source,
            firstName: $firstName,
            lastName: $lastName,
        );
    }

    // ─────────────── getRoles : mappage ──────────────────────────────────

    public function testGetRolesRetourneRoleAdminPourAdmin(): void
    {
        $user = $this->buildUser('ADMIN');

        $this->assertSame(['ROLE_ADMIN'], $user->getRoles());
    }

    public function testGetRolesRetourneRoleRhPourRh(): void
    {
        $user = $this->buildUser('RH');

        $this->assertSame(['ROLE_RH'], $user->getRoles());
    }

    public function testGetRolesRetourneRoleEmployeePourEmployee(): void
    {
        $user = $this->buildUser('EMPLOYEE');

        $this->assertSame(['ROLE_EMPLOYEE'], $user->getRoles());
    }

    public function testGetRolesRetourneRoleCandidatePourCandidate(): void
    {
        $user = $this->buildUser('CANDIDATE');

        $this->assertSame(['ROLE_CANDIDATE'], $user->getRoles());
    }

    public function testGetRolesRetourneRoleUserPourRoleInconnu(): void
    {
        $user = $this->buildUser('SUPER_BIZARRE');

        $this->assertSame(['ROLE_USER'], $user->getRoles());
    }

    public function testGetRolesAccepteRoleDejaPreffixeAvecRole(): void
    {
        $user = $this->buildUser('ROLE_CUSTOM');

        $this->assertSame(['ROLE_CUSTOM'], $user->getRoles());
    }

    public function testGetRolesInsensibleALaCasse(): void
    {
        $user = $this->buildUser('admin');

        $this->assertSame(['ROLE_ADMIN'], $user->getRoles());
    }

    // ─────────────── isEmployee ───────────────────────────────────────────

    public function testIsEmployeeRetourneTrueQuandSourceEstEmployees(): void
    {
        $user = $this->buildUser('EMPLOYEE', source: 'employees');

        $this->assertTrue($user->isEmployee());
    }

    public function testIsEmployeeRetourneFalseQuandSourceEstUsers(): void
    {
        $user = $this->buildUser('RH', source: 'users');

        $this->assertFalse($user->isEmployee());
    }

    public function testIsEmployeeRetourneFalseQuandSourceEstCandidates(): void
    {
        $user = $this->buildUser('CANDIDATE', source: 'candidates');

        $this->assertFalse($user->isEmployee());
    }

    // ─────────────── getFullName ──────────────────────────────────────────

    public function testGetFullNameRetourneNomCompletQuandDeuxChampsPresents(): void
    {
        $user = $this->buildUser('ADMIN', firstName: 'Jean', lastName: 'Dupont');

        $this->assertSame('Jean Dupont', $user->getFullName());
    }

    public function testGetFullNameRetourneNullSiPrenomManquant(): void
    {
        $user = $this->buildUser('ADMIN', firstName: null, lastName: 'Dupont');

        $this->assertNull($user->getFullName());
    }

    public function testGetFullNameRetourneNullSiNomManquant(): void
    {
        $user = $this->buildUser('ADMIN', firstName: 'Jean', lastName: null);

        $this->assertNull($user->getFullName());
    }

    public function testGetFullNameRetourneNullSiLesDeuxChampsSontNull(): void
    {
        $user = $this->buildUser('ADMIN');

        $this->assertNull($user->getFullName());
    }

    // ─────────────── getUserIdentifier ───────────────────────────────────

    public function testGetUserIdentifierRetourneUsername(): void
    {
        $user = $this->buildUser('RH', username: 'marie.rh');

        $this->assertSame('marie.rh', $user->getUserIdentifier());
    }

    // ─────────────── accesseurs basiques ─────────────────────────────────

    public function testGetIdRetourneIdentifiantNumerique(): void
    {
        $user = new DbUser(
            id: 42,
            username: 'u',
            email: null,
            password: 'p',
            role: 'RH',
        );

        $this->assertSame(42, $user->getId());
    }

    public function testGetEmailRetourneEmailOuNull(): void
    {
        $avecEmail    = $this->buildUser('ADMIN', email: 'a@b.com');
        $sansEmail    = new DbUser(1, 'u', null, 'p', 'ADMIN');

        $this->assertSame('a@b.com', $avecEmail->getEmail());
        $this->assertNull($sansEmail->getEmail());
    }

    public function testGetPasswordRetourneLeMotDePasseStocke(): void
    {
        $user = $this->buildUser('RH', password: 'sha256hash');

        $this->assertSame('sha256hash', $user->getPassword());
    }

    public function testGetRoleRetourneLaValeurBrute(): void
    {
        $user = $this->buildUser('ADMIN');

        $this->assertSame('ADMIN', $user->getRole());
    }

    public function testGetSourceRetourneUsersParDefaut(): void
    {
        $user = $this->buildUser('RH');

        $this->assertSame('users', $user->getSource());
    }

    public function testEraseCredentialsNeLancePasException(): void
    {
        $user = $this->buildUser('RH');

        // Must not throw
        $user->eraseCredentials();
        $this->addToAssertionCount(1);
    }
}
