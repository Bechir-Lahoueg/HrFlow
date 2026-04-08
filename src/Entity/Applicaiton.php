<?php

namespace App\Entity;

use App\Repository\ApplicaitonRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ApplicaitonRepository::class)]
#[ORM\Table(name: 'applications')]
#[ORM\Index(name: 'idx_applications_status', columns: ['status'])]
#[ORM\Index(name: 'idx_applications_applied_at', columns: ['applied_at'])]
#[ORM\Index(name: 'idx_applications_is_deleted', columns: ['is_deleted'])]
#[ORM\Index(name: 'idx_applications_department', columns: ['Department'])]
#[ORM\Index(name: 'idx_applications_email', columns: ['EmailAddress'])]
    #[ORM\Index(name: 'idx_applications_candidate', columns: ['candidate_id'])]
class Applicaiton
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'candidate_name', length: 255)]
    #[Assert\NotBlank(message: 'Candidate name is required')]
    #[Assert\Length(max: 255, maxMessage: 'Candidate name cannot exceed 255 characters')]
    private ?string $candidateName = null;

    #[ORM\ManyToOne(targetEntity: JobOffer::class)]
    #[ORM\JoinColumn(name: 'job_offer_id', referencedColumnName: 'id', nullable: false)]
    #[Assert\NotNull(message: 'Job offer is required')]
    private ?JobOffer $jobOffer = null;

    #[ORM\Column(name: 'cv_path', length: 500)]
    #[Assert\NotBlank(message: 'CV is required')]
    #[Assert\Length(max: 500, maxMessage: 'CV path cannot exceed 500 characters')]
    private ?string $cvPath = null;

    #[ORM\Column(name: 'cover_letter_path', length: 500, nullable: true)]
    #[Assert\Length(max: 500, maxMessage: 'Cover letter path cannot exceed 500 characters')]
    private ?string $coverLetterPath = null;

    #[ORM\Column(length: 30)]
    #[Assert\NotBlank(message: 'Status is required')]
    #[Assert\Length(max: 30, maxMessage: 'Status cannot exceed 30 characters')]
    private ?string $status = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(name: 'applied_at', type: Types::DATETIME_MUTABLE)]
    #[Assert\NotNull(message: 'Applied at is required')]
    private ?\DateTimeInterface $appliedAt = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $isDeleted = false;

    #[ORM\Column(length: 150, nullable: true)]
    #[Assert\Length(max: 150, maxMessage: 'Department cannot exceed 150 characters')]
    private ?string $department = null;

    #[ORM\Column(name: 'experience_level', length: 100, nullable: true)]
    #[Assert\Length(max: 100, maxMessage: 'Experience level cannot exceed 100 characters')]
    private ?string $experienceLevel = null;

    #[ORM\Column(name: 'EmailAddress', length: 255, nullable: true)]
    #[Assert\Length(max: 255, maxMessage: 'Email cannot exceed 255 characters')]
    #[Assert\Email(message: 'Please enter a valid email address')]
    private ?string $emailAddress = null;

    #[ORM\ManyToOne(targetEntity: Candidate::class)]
    #[ORM\JoinColumn(name: 'candidate_id', referencedColumnName: 'id', nullable: true)]
    private ?Candidate $candidate = null;

    #[ORM\ManyToOne(targetEntity: Employee::class)]
    #[ORM\JoinColumn(name: 'employee_id', referencedColumnName: 'id', nullable: true)]
    private ?Employee $employee = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100, maxMessage: 'Source cannot exceed 100 characters')]
    private ?string $source = null;

    #[ORM\OneToMany(targetEntity: Interview::class, mappedBy: 'application', cascade: ['remove'])]
    private $interviews;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCandidateName(): ?string
    {
        return $this->candidateName;
    }

    public function setCandidateName(string $candidateName): static
    {
        $this->candidateName = $candidateName;
        return $this;
    }

    public function getJobOffer(): ?JobOffer
    {
        return $this->jobOffer;
    }

    public function setJobOffer(?JobOffer $jobOffer): static
    {
        $this->jobOffer = $jobOffer;
        return $this;
    }

    public function getCvPath(): ?string
    {
        return $this->cvPath;
    }

    public function setCvPath(string $cvPath): static
    {
        $this->cvPath = $cvPath;
        return $this;
    }

    public function getCoverLetterPath(): ?string
    {
        return $this->coverLetterPath;
    }

    public function setCoverLetterPath(?string $coverLetterPath): static
    {
        $this->coverLetterPath = $coverLetterPath;
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

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;
        return $this;
    }

    public function getAppliedAt(): ?\DateTimeInterface
    {
        return $this->appliedAt;
    }

    public function setAppliedAt(\DateTimeInterface $appliedAt): static
    {
        $this->appliedAt = $appliedAt;
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

    public function getDepartment(): ?string
    {
        return $this->department;
    }

    public function setDepartment(?string $department): static
    {
        $this->department = $department;
        return $this;
    }

    public function getExperienceLevel(): ?string
    {
        return $this->experienceLevel;
    }

    public function setExperienceLevel(?string $experienceLevel): static
    {
        $this->experienceLevel = $experienceLevel;
        return $this;
    }

    public function getEmailAddress(): ?string
    {
        return $this->emailAddress;
    }

    public function setEmailAddress(?string $emailAddress): static
    {
        $this->emailAddress = $emailAddress;
        return $this;
    }

    public function getCandidate(): ?Candidate
    {
        return $this->candidate;
    }

    public function setCandidate(?Candidate $candidate): static
    {
        $this->candidate = $candidate;
        return $this;
    }

    public function getEmployee(): ?Employee
    {
        return $this->employee;
    }

    public function setEmployee(?Employee $employee): static
    {
        $this->employee = $employee;
        return $this;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setSource(?string $source): static
    {
        $this->source = $source;
        return $this;
    }

    public function getStatusLabel(): string
    {
        return match($this->status) {
            'PENDING' => 'En attente',
            'REVIEWING' => 'En revue',
            'INTERVIEW' => 'Entretien',
            'OFFER' => 'Offre',
            'HIRED' => 'Recrute',
            'REJECTED' => 'Rejete',
            default => $this->status,
        };
    }

    public function getStatusColor(): string
    {
        return match($this->status) {
            'PENDING' => 'amber',
            'REVIEWING' => 'blue',
            'INTERVIEW' => 'purple',
            'OFFER' => 'emerald',
            'HIRED' => 'teal',
            'REJECTED' => 'red',
            default => 'slate',
        };
    }
}
