<?php

namespace App\Entity\Vault\Catalog;

use App\Entity\Vault\Collection\Edition;
use App\Repository\Vault\Catalog\RecordRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

#[ORM\Entity(repositoryClass: RecordRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_RECORD', columns: ['artist_id', 'title', 'year_original'])]
class Record
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(length: 255)]
    #[Gedmo\Slug(fields: ['title'])]
    private ?string $slug = null;

    #[ORM\Column(length: 255)]
    private ?string $yearOriginal = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $coverUrl = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $coverHash = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $discogsMasterId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $discogsReleaseId = null;

    #[ORM\Column]
    private ?int $sourceConfidence = null;

    #[ORM\ManyToOne(inversedBy: 'records')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Artist $artist = null;

    /**
     * @var Collection<int, Edition>
     */
    #[ORM\OneToMany(targetEntity: Edition::class, mappedBy: 'record')]
    private Collection $editions;

    public function __construct()
    {
        $this->editions = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getYearOriginal(): ?string
    {
        return $this->yearOriginal;
    }

    public function setYearOriginal(string $yearOriginal): static
    {
        $this->yearOriginal = $yearOriginal;

        return $this;
    }

    public function getCoverUrl(): ?string
    {
        return $this->coverUrl;
    }

    public function setCoverUrl(?string $coverUrl): static
    {
        $this->coverUrl = $coverUrl;

        return $this;
    }

    public function getCoverHash(): ?string
    {
        return $this->coverHash;
    }

    public function setCoverHash(?string $coverHash): static
    {
        $this->coverHash = $coverHash;

        return $this;
    }

    public function getDiscogsMasterId(): ?string
    {
        return $this->discogsMasterId;
    }

    public function setDiscogsMasterId(?string $discogsMasterId): static
    {
        $this->discogsMasterId = $discogsMasterId;

        return $this;
    }

    public function getDiscogsReleaseId(): ?string
    {
        return $this->discogsReleaseId;
    }

    public function setDiscogsReleaseId(?string $discogsReleaseId): static
    {
        $this->discogsReleaseId = $discogsReleaseId;

        return $this;
    }

    public function getSourceConfidence(): ?int
    {
        return $this->sourceConfidence;
    }

    public function setSourceConfidence(int $sourceConfidence): static
    {
        $this->sourceConfidence = $sourceConfidence;

        return $this;
    }

    public function getArtist(): ?Artist
    {
        return $this->artist;
    }

    public function setArtist(?Artist $artist): static
    {
        $this->artist = $artist;

        return $this;
    }

    /**
     * @return Collection<int, Edition>
     */
    public function getEditions(): Collection
    {
        return $this->editions;
    }

    public function addEdition(Edition $edition): static
    {
        if (!$this->editions->contains($edition)) {
            $this->editions->add($edition);
            $edition->setRecord($this);
        }

        return $this;
    }

    public function removeEdition(Edition $edition): static
    {
        if ($this->editions->removeElement($edition)) {
            // set the owning side to null (unless already changed)
            if ($edition->getRecord() === $this) {
                $edition->setRecord(null);
            }
        }

        return $this;
    }
}
