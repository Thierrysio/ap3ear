<?php

namespace App\Entity;

use App\Repository\TCompetitionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TCompetitionRepository::class)]
class TCompetition
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?\DateTime $dateDebut = null;

    #[ORM\Column]
    private ?\DateTime $datefin = null;

    /**
     * @var Collection<int, TEpreuve>
     */
    #[ORM\OneToMany(targetEntity: TEpreuve::class, mappedBy: 'laCompetition')]
    private Collection $lestEpreuves;

    /**
     * @var Collection<int, TEquipe>
     */
    #[ORM\ManyToMany(targetEntity: TEquipe::class, inversedBy: 'competitions')]
    #[ORM\JoinTable(name: 'tcompetition_tequipe')]
    private Collection $teams;

    public function __construct()
    {
        $this->lestEpreuves = new ArrayCollection();
        $this->teams = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDateDebut(): ?\DateTime
    {
        return $this->dateDebut;
    }

    public function setDateDebut(\DateTime $dateDebut): static
    {
        $this->dateDebut = $dateDebut;

        return $this;
    }

    public function getDatefin(): ?\DateTime
    {
        return $this->datefin;
    }

    public function setDatefin(\DateTime $datefin): static
    {
        $this->datefin = $datefin;

        return $this;
    }

    /**
     * @return Collection<int, TEpreuve>
     */
    public function getLestEpreuves(): Collection
    {
        return $this->lestEpreuves;
    }

    public function addLestEpreufe(TEpreuve $lestEpreufe): static
    {
        if (!$this->lestEpreuves->contains($lestEpreufe)) {
            $this->lestEpreuves->add($lestEpreufe);
            $lestEpreufe->setLaCompetition($this);
        }

        return $this;
    }

    public function removeLestEpreufe(TEpreuve $lestEpreufe): static
    {
        if ($this->lestEpreuves->removeElement($lestEpreufe)) {
            // set the owning side to null (unless already changed)
            if ($lestEpreufe->getLaCompetition() === $this) {
                $lestEpreufe->setLaCompetition(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, TEquipe>
     */
    public function getTeams(): Collection
    {
        return $this->teams;
    }

    public function addTeam(TEquipe $team): static
    {
        if (!$this->teams->contains($team)) {
            $this->teams->add($team);
            $team->addCompetition($this);
        }

        return $this;
    }

    public function removeTeam(TEquipe $team): static
    {
        if ($this->teams->removeElement($team)) {
            $team->removeCompetition($this);
        }

        return $this;
    }
}
