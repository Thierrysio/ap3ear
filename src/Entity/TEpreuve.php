<?php

namespace App\Entity;

use App\Repository\TEpreuveRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TEpreuveRepository::class)]
class TEpreuve
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nom = null;

    #[ORM\ManyToOne(inversedBy: 'lestEpreuves')]
    private ?TCompetition $laCompetition = null;

    /**
     * @var Collection<int, TScore>
     */
    #[ORM\OneToMany(targetEntity: TScore::class, mappedBy: 'latEpreuve')]
    private Collection $lestScores;

    #[ORM\Column]
    private ?\DateTime $datedebut = null;

    #[ORM\Column]
    private ?int $dureemax = null;

    /**
     * @var Collection<int, Tflag>
     */
    #[ORM\OneToMany(targetEntity: Tflag::class, mappedBy: 'epreuve')]
    private Collection $epreuve_id;

    public function __construct()
    {
        $this->lestScores = new ArrayCollection();
        $this->epreuve_id = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getLaCompetition(): ?TCompetition
    {
        return $this->laCompetition;
    }

    public function setLaCompetition(?TCompetition $laCompetition): static
    {
        $this->laCompetition = $laCompetition;

        return $this;
    }

    /**
     * @return Collection<int, TScore>
     */
    public function getLestScores(): Collection
    {
        return $this->lestScores;
    }

    public function addLestScore(TScore $lestScore): static
    {
        if (!$this->lestScores->contains($lestScore)) {
            $this->lestScores->add($lestScore);
            $lestScore->setLatEpreuve($this);
        }

        return $this;
    }

    public function removeLestScore(TScore $lestScore): static
    {
        if ($this->lestScores->removeElement($lestScore)) {
            // set the owning side to null (unless already changed)
            if ($lestScore->getLatEpreuve() === $this) {
                $lestScore->setLatEpreuve(null);
            }
        }

        return $this;
    }

    public function getDatedebut(): ?\DateTime
    {
        return $this->datedebut;
    }

    public function setDatedebut(\DateTime $datedebut): static
    {
        $this->datedebut = $datedebut;

        return $this;
    }

    public function getDureemax(): ?int
    {
        return $this->dureemax;
    }

    public function setDureemax(int $dureemax): static
    {
        $this->dureemax = $dureemax;

        return $this;
    }

    /**
     * @return Collection<int, Tflag>
     */
    public function getEpreuveId(): Collection
    {
        return $this->epreuve_id;
    }

    public function addEpreuveId(Tflag $epreuveId): static
    {
        if (!$this->epreuve_id->contains($epreuveId)) {
            $this->epreuve_id->add($epreuveId);
            $epreuveId->setEpreuve($this);
        }

        return $this;
    }

    public function removeEpreuveId(Tflag $epreuveId): static
    {
        if ($this->epreuve_id->removeElement($epreuveId)) {
            // set the owning side to null (unless already changed)
            if ($epreuveId->getEpreuve() === $this) {
                $epreuveId->setEpreuve(null);
            }
        }

        return $this;
    }
}
