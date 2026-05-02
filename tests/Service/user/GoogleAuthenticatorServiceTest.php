<?php

namespace App\Tests\Service\user;

use App\Security\DbUser;
use App\Service\Security\GoogleAuthenticatorService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour GoogleAuthenticatorService.
 *
 * Couvre : génération de secret, URI de provisionnement,
 * validation de code TOTP, activation / désactivation 2FA.
 */
#[AllowMockObjectsWithoutExpectations]
class GoogleAuthenticatorServiceTest extends TestCase
{
    private Connection&MockObject          $connection;
    private GoogleAuthenticatorService     $service;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->service    = new GoogleAuthenticatorService($this->connection);
    }

    // ─── Fabrique DbUser ─────────────────────────────────────────────────

    private function makeUser(
        int $id = 1,
        string $source = 'users',
        string $username = 'jdupont',
        ?string $email = 'jdupont@hrflow.io'
    ): DbUser {
        return new DbUser(
            id: $id,
            username: $username,
            email: $email,
            password: 'hash',
            role: 'RH',
            source: $source,
        );
    }

    // ─── generateSecret ───────────────────────────────────────────────────

    public function testGenerateSecretRetourneUneChaineNonVide(): void
    {
        $secret = $this->service->generateSecret();

        $this->assertNotEmpty($secret);
    }

    public function testGenerateSecretLongueurParDefautEstTrenteDeuxCaracteres(): void
    {
        $secret = $this->service->generateSecret();

        $this->assertSame(32, strlen($secret));
    }

    public function testGenerateSecretLongueurPersonnalisee(): void
    {
        $secret = $this->service->generateSecret(16);

        $this->assertSame(16, strlen($secret));
    }

    public function testGenerateSecretContiendtUniquementDesCaracteresBase32(): void
    {
        $base32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = $this->service->generateSecret(64);

        foreach (str_split($secret) as $char) {
            $this->assertStringContainsString(
                $char,
                $base32,
                "Le caractère '{$char}' n'est pas dans l'alphabet BASE32."
            );
        }
    }

    public function testGenerateSecretEstDifferentAChaquAppel(): void
    {
        $s1 = $this->service->generateSecret();
        $s2 = $this->service->generateSecret();

        // Extrêmement improbable d'être identiques
        $this->assertNotSame($s1, $s2);
    }

    // ─── getProvisioningUri ───────────────────────────────────────────────

    public function testGetProvisioningUriRetourneUnOtpauthUri(): void
    {
        $user   = $this->makeUser(email: 'jdupont@hrflow.io');
        $uri    = $this->service->getProvisioningUri($user, 'JBSWY3DPEHPK3PXP');

        $this->assertStringStartsWith('otpauth://totp/', $uri);
    }

    public function testGetProvisioningUriContientLIssuerHrFlow(): void
    {
        $user = $this->makeUser();
        $uri  = $this->service->getProvisioningUri($user, 'JBSWY3DPEHPK3PXP');

        $this->assertStringContainsString('issuer=HrFlow', $uri);
    }

    public function testGetProvisioningUriContientLeSecret(): void
    {
        $secret = 'JBSWY3DPEHPK3PXP';
        $user   = $this->makeUser();
        $uri    = $this->service->getProvisioningUri($user, $secret);

        $this->assertStringContainsString('secret=' . rawurlencode($secret), $uri);
    }

    public function testGetProvisioningUriUtiliseEmailSiDisponible(): void
    {
        $user = $this->makeUser(email: 'rh@hrflow.io');
        $uri  = $this->service->getProvisioningUri($user, 'SECRET');

        $this->assertStringContainsString(rawurlencode('HrFlow:rh@hrflow.io'), $uri);
    }

    public function testGetProvisioningUriUtiliseUsernameSiEmailNull(): void
    {
        $user = $this->makeUser(email: null, username: 'mrh');
        $uri  = $this->service->getProvisioningUri($user, 'SECRET');

        $this->assertStringContainsString(rawurlencode('HrFlow:mrh'), $uri);
    }

    // ─── verifyCode : codes invalides ────────────────────────────────────

    public function testVerifyCodeRetourneFalseAvecCodeVide(): void
    {
        $this->assertFalse($this->service->verifyCode('JBSWY3DPEHPK3PXP', ''));
    }

    public function testVerifyCodeRetourneFalseAvecCodeTropCourt(): void
    {
        $this->assertFalse($this->service->verifyCode('JBSWY3DPEHPK3PXP', '12345'));
    }

    public function testVerifyCodeRetourneFalseAvecCodeTropLong(): void
    {
        $this->assertFalse($this->service->verifyCode('JBSWY3DPEHPK3PXP', '1234567'));
    }

    public function testVerifyCodeRetourneFalseAvecCodeNonNumerique(): void
    {
        $this->assertFalse($this->service->verifyCode('JBSWY3DPEHPK3PXP', 'abcdef'));
    }

    public function testVerifyCodeRetourneFalseAvecCodeMixteLettreCiffre(): void
    {
        // La regex retire les non-chiffres, ce qui peut laisser moins de 6 chiffres
        $this->assertFalse($this->service->verifyCode('JBSWY3DPEHPK3PXP', 'ab1234'));
    }

    // ─── verifyForUser : secret absent ───────────────────────────────────

    public function testVerifyForUserRetourneFalseQuandSecretAbsent(): void
    {
        $user = $this->makeUser();
        // getSecret appelle connection->fetchOne, retourne false (introuvable)
        $this->connection->method('fetchOne')->willReturn(false);

        $this->assertFalse($this->service->verifyForUser($user, '123456'));
    }

    public function testVerifyForUserRetourneFalseQuandSecretEstChaineVide(): void
    {
        $user = $this->makeUser();
        $this->connection->method('fetchOne')->willReturn('');

        $this->assertFalse($this->service->verifyForUser($user, '123456'));
    }

    // ─── isEnabled ────────────────────────────────────────────────────────

    public function testIsEnabledRetourneTrueQuandEnregistrementVaut1(): void
    {
        $user = $this->makeUser();
        $this->connection->method('fetchOne')->willReturn('1');

        $this->assertTrue($this->service->isEnabled($user));
    }

    public function testIsEnabledRetourneFalseQuandEnregistrementVaut0(): void
    {
        $user = $this->makeUser();
        $this->connection->method('fetchOne')->willReturn('0');

        $this->assertFalse($this->service->isEnabled($user));
    }

    public function testIsEnabledRetourneFalseQuandAucunEnregistrement(): void
    {
        $user = $this->makeUser();
        $this->connection->method('fetchOne')->willReturn(false);

        $this->assertFalse($this->service->isEnabled($user));
    }

    // ─── enableForUser ────────────────────────────────────────────────────

    public function testEnableForUserInsereLigneQuandAbsente(): void
    {
        $user = $this->makeUser();

        $this->connection->method('fetchOne')->willReturn(false); // pas de ligne existante
        $this->connection->expects($this->once())->method('insert');
        $this->connection->expects($this->never())->method('update');

        $this->service->enableForUser($user, 'NEWSECRET');
    }

    public function testEnableForUserMetAJourLigneQuandDejaExistante(): void
    {
        $user = $this->makeUser();

        $this->connection->method('fetchOne')->willReturn('5'); // id existant
        $this->connection->expects($this->once())->method('update');
        $this->connection->expects($this->never())->method('insert');

        $this->service->enableForUser($user, 'UPDATEDSECRET');
    }

    // ─── disableForUser ───────────────────────────────────────────────────

    public function testDisableForUserAppelleUpdate(): void
    {
        $user = $this->makeUser();

        $this->connection->expects($this->once())->method('update')
            ->with(
                'user_two_factor',
                $this->callback(fn($data) => isset($data['enabled']) && $data['enabled'] === 0),
                $this->anything(),
            );

        $this->service->disableForUser($user);
    }

    // ─── getSecret ────────────────────────────────────────────────────────

    public function testGetSecretRetourneLeSecretQuandPresentEtActif(): void
    {
        $user = $this->makeUser();
        $this->connection->method('fetchOne')->willReturn('MYSECRETKEY');

        $this->assertSame('MYSECRETKEY', $this->service->getSecret($user));
    }

    public function testGetSecretRetourneNullQuandSecretAbsent(): void
    {
        $user = $this->makeUser();
        $this->connection->method('fetchOne')->willReturn(false);

        $this->assertNull($this->service->getSecret($user));
    }

    public function testGetSecretRetourneNullQuandSecretEstChaineVide(): void
    {
        $user = $this->makeUser();
        $this->connection->method('fetchOne')->willReturn('');

        $this->assertNull($this->service->getSecret($user));
    }
}
