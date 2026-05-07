<?php

namespace App\Tests\Service\Recrutement;

use App\Entity\Recrutement\Candidate;
use App\Service\Recrutement\CandidateManager;
use PHPUnit\Framework\TestCase;

class CandidateManagerTest extends TestCase
{
    public function testValidCandidate()
    {
        $candidate = new Candidate();
        $candidate->setUsername('john_doe');
        $candidate->setEmail('john.doe@gmail.com');
        $candidate->setPassword('securePassword123');

        $manager = new CandidateManager();

        $this->assertTrue($manager->validate($candidate));
    }

    public function testCandidateWithInvalidEmail()
    {
        $this->expectException(\InvalidArgumentException::class);

        $candidate = new Candidate();
        $candidate->setUsername('john_doe');
        $candidate->setEmail('email_invalide');
        $candidate->setPassword('securePassword123');

        $manager = new CandidateManager();
        $manager->validate($candidate);
    }

    public function testCandidateWithShortPassword()
    {
        $this->expectException(\InvalidArgumentException::class);

        $candidate = new Candidate();
        $candidate->setUsername('john_doe');
        $candidate->setEmail('john.doe@gmail.com');
        $candidate->setPassword('123');

        $manager = new CandidateManager();
        $manager->validate($candidate);
    }

    public function testCandidateWithPasswordExactlyEightChars()
    {
        $candidate = new Candidate();
        $candidate->setUsername('john_doe');
        $candidate->setEmail('john.doe@gmail.com');
        $candidate->setPassword('12345678');

        $manager = new CandidateManager();

        $this->assertTrue($manager->validate($candidate));
    }

    public function testCandidateWithPasswordSevenChars()
    {
        $this->expectException(\InvalidArgumentException::class);

        $candidate = new Candidate();
        $candidate->setUsername('john_doe');
        $candidate->setEmail('john.doe@gmail.com');
        $candidate->setPassword('1234567');

        $manager = new CandidateManager();
        $manager->validate($candidate);
    }

    public function testCandidateWithEmptyEmail()
    {
        $this->expectException(\InvalidArgumentException::class);

        $candidate = new Candidate();
        $candidate->setUsername('john_doe');
        $candidate->setEmail('');
        $candidate->setPassword('securePassword123');

        $manager = new CandidateManager();
        $manager->validate($candidate);
    }

    public function testCandidateWithNullEmail()
    {
        $this->expectException(\InvalidArgumentException::class);

        $candidate = new Candidate();
        $candidate->setUsername('john_doe');
        $candidate->setPassword('securePassword123');

        $manager = new CandidateManager();
        $manager->validate($candidate);
    }

    public function testCandidateWithEmailWithoutAtSymbol()
    {
        $this->expectException(\InvalidArgumentException::class);

        $candidate = new Candidate();
        $candidate->setUsername('john_doe');
        $candidate->setEmail('johndoe.com');
        $candidate->setPassword('securePassword123');

        $manager = new CandidateManager();
        $manager->validate($candidate);
    }

    public function testCandidateWithEmailWithoutDomain()
    {
        $this->expectException(\InvalidArgumentException::class);

        $candidate = new Candidate();
        $candidate->setUsername('john_doe');
        $candidate->setEmail('john@');
        $candidate->setPassword('securePassword123');

        $manager = new CandidateManager();
        $manager->validate($candidate);
    }
}
