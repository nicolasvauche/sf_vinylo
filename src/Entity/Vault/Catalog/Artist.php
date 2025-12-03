<?php

namespace App\Entity\Vault\Catalog;

use App\Repository\Vault\Catalog\ArtistRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

#[ORM\Entity(repositoryClass: ArtistRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_ARTIST', columns: ['name_canonical', 'country_code'])]
class Artist
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    private ?string $nameCanonical = null;

    #[ORM\Column(length: 255)]
    #[Gedmo\Slug(fields: ['nameCanonical'])]
    private ?string $slug = null;

    #[ORM\Column(length: 255)]
    private ?string $countryCode = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $countryName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $discogsArtistId = null;

    /**
     * @var Collection<int, Record>
     */
    #[ORM\OneToMany(targetEntity: Record::class, mappedBy: 'artist', orphanRemoval: true)]
    private Collection $records;

    public function __construct()
    {
        $this->records = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        $this->nameCanonical = $this->canonicalize($name);

        return $this;
    }

    public function getNameCanonical(): ?string
    {
        return $this->nameCanonical;
    }

    public function setNameCanonical(string $nameCanonical): static
    {
        $this->nameCanonical = $nameCanonical;

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

    public function getCountryCode(): ?string
    {
        return $this->countryCode;
    }

    public function setCountryCode(string $countryCode): static
    {
        $this->countryCode = $countryCode;

        return $this;
    }

    public function getCountryName(): ?string
    {
        return $this->countryName;
    }

    public function setCountryName(?string $countryName): static
    {
        $this->countryName = $countryName;

        return $this;
    }

    public function getDiscogsArtistId(): ?string
    {
        return $this->discogsArtistId;
    }

    public function setDiscogsArtistId(?string $discogsArtistId): static
    {
        $this->discogsArtistId = $discogsArtistId;

        return $this;
    }

    /**
     * @return Collection<int, Record>
     */
    public function getRecords(): Collection
    {
        return $this->records;
    }

    public function addRecord(Record $record): static
    {
        if (!$this->records->contains($record)) {
            $this->records->add($record);
            $record->setArtist($this);
        }

        return $this;
    }

    public function removeRecord(Record $record): static
    {
        if ($this->records->removeElement($record)) {
            // set the owning side to null (unless already changed)
            if ($record->getArtist() === $this) {
                $record->setArtist(null);
            }
        }

        return $this;
    }

    private function canonicalize(string $name): string
    {
        $n = trim($name);
        $n = preg_replace('/\s+/u', ' ', $n);

        return mb_strtolower($n, 'UTF-8');
    }
}
