<?php

namespace App\Entity;

use App\Repository\JobOfferRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: JobOfferRepository::class)]
#[ORM\Table(name: 'job_offer')]
#[ORM\Index(name: 'idx_job_offer_status', columns: ['status'])]
#[ORM\Index(name: 'idx_job_offer_department', columns: ['department'])]
#[ORM\Index(name: 'idx_job_offer_location', columns: ['location'])]
#[ORM\Index(name: 'idx_job_offer_is_deleted', columns: ['is_deleted'])]
class JobOffer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Title is required')]
    #[Assert\Length(max: 255, maxMessage: 'Title cannot exceed 255 characters')]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'Description is required')]
    private ?string $description = null;

    #[ORM\Column(length: 150, nullable: true)]
    #[Assert\Length(max: 150, maxMessage: 'Department cannot exceed 150 characters')]
    private ?string $department = null;

    #[ORM\Column(length: 150, nullable: true)]
    #[Assert\Length(max: 150, maxMessage: 'Location cannot exceed 150 characters')]
    private ?string $location = null;

    #[ORM\Column(name: 'employment_type', length: 100, nullable: true)]
    #[Assert\Length(max: 100, maxMessage: 'Employment type cannot exceed 100 characters')]
    private ?string $employmentType = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0.00'])]
    #[Assert\NotNull(message: 'Minimum salary is required')]
    #[Assert\GreaterThanOrEqual(value: 0, message: 'Minimum salary must be positive')]
    private ?string $salaryMin = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0.00'])]
    #[Assert\NotNull(message: 'Maximum salary is required')]
    #[Assert\GreaterThanOrEqual(propertyPath: 'salaryMin', message: 'Maximum salary must be greater than or equal to minimum salary')]
    private ?string $salaryMax = '0.00';

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank(message: 'Status is required')]
    #[Assert\Length(max: 20, maxMessage: 'Status cannot exceed 20 characters')]
    private ?string $status = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Assert\NotNull(message: 'Created at is required')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(name: 'created_by', type: Types::INTEGER, nullable: false)]
    #[Assert\NotNull(message: 'Created by is required')]
    private ?int $createdBy = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $isDeleted = false;

    #[ORM\OneToMany(targetEntity: Applicaiton::class, mappedBy: 'jobOffer', cascade: ['remove'])]
    private $applications;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): static
    {
        $this->id = $id;
        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getDepartment(): ?string
    {
        return $this->department;
    }

    public function setDepartment(?string $department): static
    {
        $this->department = $department;
        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): static
    {
        $this->location = $location;
        return $this;
    }

    public function getEmploymentType(): ?string
    {
        return $this->employmentType;
    }

    public function setEmploymentType(?string $employmentType): static
    {
        $this->employmentType = $employmentType;
        return $this;
    }

    public function getSalaryMin(): ?string
    {
        return $this->salaryMin;
    }

    public function setSalaryMin(string $salaryMin): static
    {
        $this->salaryMin = $salaryMin;
        return $this;
    }

    public function getSalaryMax(): ?string
    {
        return $this->salaryMax;
    }

    public function setSalaryMax(string $salaryMax): static
    {
        $this->salaryMax = $salaryMax;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getCreatedBy(): ?int
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?int $createdBy): static
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    public function isDeleted(): bool
    {
        return $this->isDeleted;
    }

    public function setIsDeleted(bool $isDeleted): static
    {
        $this->isDeleted = $isDeleted;
        return $this;
    }

    public function getApplications()
    {
        return $this->applications;
    }
}
