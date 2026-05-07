<?php

namespace App\Service\Security;

use App\Security\DbUser;
use Doctrine\DBAL\Connection;

final class GoogleAuthenticatorService
{
    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function __construct(private readonly Connection $connection)
    {
    }

    public function isEnabled(DbUser $user): bool
    {
        $enabled = $this->connection->fetchOne(
            'SELECT enabled FROM user_two_factor WHERE user_source = :source AND user_id = :userId LIMIT 1',
            [
                'source' => $user->getSource(),
                'userId' => $user->getId(),
            ]
        );

        return (int) $enabled === 1;
    }

    public function getSecret(DbUser $user): ?string
    {
        $secret = $this->connection->fetchOne(
            'SELECT secret FROM user_two_factor WHERE user_source = :source AND user_id = :userId AND enabled = 1 LIMIT 1',
            [
                'source' => $user->getSource(),
                'userId' => $user->getId(),
            ]
        );

        return is_string($secret) && $secret !== '' ? $secret : null;
    }

    public function enableForUser(DbUser $user, string $secret): void
    {
        $existing = $this->connection->fetchOne(
            'SELECT id FROM user_two_factor WHERE user_source = :source AND user_id = :userId LIMIT 1',
            [
                'source' => $user->getSource(),
                'userId' => $user->getId(),
            ]
        );

        if ($existing) {
            $this->connection->update('user_two_factor', [
                'secret' => $secret,
                'enabled' => 1,
                'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ], [
                'id' => (int) $existing,
            ]);

            return;
        }

        $this->connection->insert('user_two_factor', [
            'user_source' => $user->getSource(),
            'user_id' => $user->getId(),
            'secret' => $secret,
            'enabled' => 1,
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
    }

    public function disableForUser(DbUser $user): void
    {
        $this->connection->update('user_two_factor', [
            'enabled' => 0,
            'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ], [
            'user_source' => $user->getSource(),
            'user_id' => $user->getId(),
        ]);
    }

    public function generateSecret(int $length = 32): string
    {
        $chars = self::BASE32_ALPHABET;
        $max = strlen($chars) - 1;
        $secret = '';

        for ($i = 0; $i < $length; $i++) {
            $secret .= $chars[random_int(0, $max)];
        }

        return $secret;
    }

    public function getProvisioningUri(DbUser $user, string $secret): string
    {
        $identifier = $user->getEmail() ?: $user->getUsername();
        $label = rawurlencode('HrFlow:' . $identifier);
        $issuer = rawurlencode('HrFlow');

        return sprintf(
            'otpauth://totp/%s?secret=%s&issuer=%s&algorithm=SHA1&digits=6&period=30',
            $label,
            rawurlencode($secret),
            $issuer
        );
    }

    public function verifyForUser(DbUser $user, string $code): bool
    {
        $secret = $this->getSecret($user);
        if ($secret === null) {
            return false;
        }

        return $this->verifyCode($secret, $code);
    }

    public function verifyCode(string $secret, string $code, int $window = 1): bool
    {
        $normalizedCode = preg_replace('/\D+/', '', $code);
        if (!is_string($normalizedCode) || strlen($normalizedCode) !== 6) {
            return false;
        }

        $timeStep = (int) floor(time() / 30);
        for ($offset = -$window; $offset <= $window; $offset++) {
            $candidate = $this->generateCodeForCounter($secret, $timeStep + $offset);
            if (hash_equals($candidate, $normalizedCode)) {
                return true;
            }
        }

        return false;
    }

    private function generateCodeForCounter(string $secret, int $counter): string
    {
        $secretKey = $this->base32Decode($secret);
        $binaryCounter = pack('N2', 0, $counter);
        $hash = hash_hmac('sha1', $binaryCounter, $secretKey, true);

        $offset = ord(substr($hash, -1)) & 0x0F;
        $segment = substr($hash, $offset, 4);
        $unpacked = unpack('N', $segment);
        $value = ($unpacked !== false ? $unpacked[1] : 0) & 0x7FFFFFFF;

        return str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT);
    }

    private function base32Decode(string $secret): string
    {
        $secret = strtoupper(preg_replace('/[^A-Z2-7]/', '', $secret) ?? '');
        $bits = '';

        for ($i = 0, $len = strlen($secret); $i < $len; $i++) {
            $index = strpos(self::BASE32_ALPHABET, $secret[$i]);
            if ($index === false) {
                continue;
            }
            $bits .= str_pad(decbin($index), 5, '0', STR_PAD_LEFT);
        }

        $binary = '';
        for ($i = 0, $len = strlen($bits); $i + 8 <= $len; $i += 8) {
            $binary .= chr((int) bindec(substr($bits, $i, 8)) & 0xFF);
        }

        return $binary;
    }
}
