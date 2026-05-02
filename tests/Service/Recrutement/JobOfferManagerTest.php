<?php

namespace App\Tests\Service\Recrutement;

use App\Entity\Recrutement\JobOffer;
use App\Service\Recrutement\JobOfferManager;
use PHPUnit\Framework\TestCase;

class JobOfferManagerTest extends TestCase
{
    public function testValidJobOffer()
    {
        $jobOffer = new JobOffer();
        $jobOffer->setTitle('Software Engineer');
        $jobOffer->setDescription('Develop awesome software');
        $jobOffer->setSalaryMin('3000');
        $jobOffer->setSalaryMax('5000');
        $jobOffer->setStatus('open');

        $manager = new JobOfferManager();

        $this->assertTrue($manager->validate($jobOffer));
    }

    public function testJobOfferWithoutTitle()
    {
        $this->expectException(\InvalidArgumentException::class);

        $jobOffer = new JobOffer();
        $jobOffer->setDescription('Develop awesome software');
        $jobOffer->setSalaryMin('3000');
        $jobOffer->setSalaryMax('5000');

        $manager = new JobOfferManager();
        $manager->validate($jobOffer);
    }

    public function testJobOfferWithSalaryMaxLessThanMin()
    {
        $this->expectException(\InvalidArgumentException::class);

        $jobOffer = new JobOffer();
        $jobOffer->setTitle('Software Engineer');
        $jobOffer->setDescription('Develop awesome software');
        $jobOffer->setSalaryMin('5000');
        $jobOffer->setSalaryMax('3000');

        $manager = new JobOfferManager();
        $manager->validate($jobOffer);
    }

    public function testJobOfferWithEqualSalaryMinAndMax()
    {
        $jobOffer = new JobOffer();
        $jobOffer->setTitle('Software Engineer');
        $jobOffer->setDescription('Develop awesome software');
        $jobOffer->setSalaryMin('4000');
        $jobOffer->setSalaryMax('4000');
        $jobOffer->setStatus('open');

        $manager = new JobOfferManager();

        $this->assertTrue($manager->validate($jobOffer));
    }

    public function testJobOfferWithTitleOnlySpaces()
    {
        $this->expectException(\InvalidArgumentException::class);

        $jobOffer = new JobOffer();
        $jobOffer->setTitle('   ');
        $jobOffer->setDescription('Develop awesome software');
        $jobOffer->setSalaryMin('3000');
        $jobOffer->setSalaryMax('5000');

        $manager = new JobOfferManager();
        $manager->validate($jobOffer);
    }

    public function testJobOfferWithNullSalaryMin()
    {
        $this->expectException(\InvalidArgumentException::class);

        $jobOffer = new JobOffer();
        $jobOffer->setTitle('Software Engineer');
        $jobOffer->setDescription('Develop awesome software');
        $jobOffer->setSalaryMin('0.00');
        $jobOffer->setSalaryMax('-1000');

        $manager = new JobOfferManager();
        $manager->validate($jobOffer);
    }

    public function testJobOfferWithNegativeSalary()
    {
        $this->expectException(\InvalidArgumentException::class);

        $jobOffer = new JobOffer();
        $jobOffer->setTitle('Software Engineer');
        $jobOffer->setDescription('Develop awesome software');
        $jobOffer->setSalaryMin('-1000');
        $jobOffer->setSalaryMax('5000');

        $manager = new JobOfferManager();
        $manager->validate($jobOffer);
    }
}
