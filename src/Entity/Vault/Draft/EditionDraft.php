<?php

namespace App\Entity\Vault\Draft;

use App\Entity\User\User;
use App\Repository\Vault\Draft\EditionDraftRepository;
use App\ValueObject\Vault\AddRecord\DraftStatus;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

#[ORM\Entity(repositoryClass: EditionDraftRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EDITION_DRAFT', columns: [
    'owner_id',
    'artist_canonical',
    'record_canonical',
])]
#[ORM\Index(name: 'idx_draft_status', columns: ['status'])]
#[ORM\Index(name: 'idx_draft_expires_at', columns: ['expires_at'])]
class EditionDraft
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $artistCanonical = null;

    #[ORM\Column(length: 255)]
    private ?string $recordCanonical = null;

    #[ORM\Column(enumType: DraftStatus::class)]
    private DraftStatus $status = DraftStatus::PENDING;

    #[ORM\Column(type: Types::JSON)]
    private array $input = [];

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $discogs = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $ai = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $resolved = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $duplicateProbe = null;

    #[ORM\Column]
    private int $attempts = 0;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $lastError = null;

    #[ORM\Column]
    #[Gedmo\Timestampable(on: 'create')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    #[Gedmo\Timestampable(on: 'update')]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $owner = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getArtistCanonical(): ?string
    {
        return $this->artistCanonical;
    }

    public function setArtistCanonical(string $artistCanonical): static
    {
        $this->artistCanonical = $artistCanonical;

        return $this;
    }

    public function getRecordCanonical(): ?string
    {
        return $this->recordCanonical;
    }

    public function setRecordCanonical(string $recordCanonical): static
    {
        $this->recordCanonical = $recordCanonical;

        return $this;
    }

    public function getStatus(): DraftStatus
    {
        return $this->status;
    }

    public function setStatus(DraftStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getInput(): array
    {
        return $this->input;
    }

    public function setInput(array $input): static
    {
        $this->input = $input;

        return $this;
    }

    public function getDiscogs(): ?array
    {
        return $this->discogs;
    }

    public function setDiscogs(?array $discogs): static
    {
        $this->discogs = $discogs;

        return $this;
    }

    public function getAi(): ?array
    {
        return $this->ai;
    }

    public function setAi(?array $ai): static
    {
        $this->ai = $ai;

        return $this;
    }

    public function getResolved(): ?array
    {
        return $this->resolved;
    }

    public function setResolved(?array $resolved): static
    {
        $this->resolved = $resolved;

        return $this;
    }

    public function getDuplicateProbe(): ?array
    {
        return $this->duplicateProbe;
    }

    public function setDuplicateProbe(?array $duplicateProbe): static
    {
        $this->duplicateProbe = $duplicateProbe;

        return $this;
    }

    public function getAttempts(): int
    {
        return $this->attempts;
    }

    public function incAttempts(): static
    {
        $this->attempts++;

        return $this;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function setLastError(?string $lastError): static
    {
        $this->lastError = $lastError;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(\DateTimeImmutable $expiresAt): static
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): static
    {
        $this->owner = $owner;

        return $this;
    }
}
