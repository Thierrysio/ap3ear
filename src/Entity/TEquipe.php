<?php

namespace App\Entity;

use App\Repository\TEquipeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TEquipeRepository::class)]
class TEquipe
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nom = null;

    /**
     * @var Collection<int, User>
     */
    #[ORM\OneToMany(targetEntity: User::class, mappedBy: 'latEquipe')]
    private Collection $lesUsers;

    /**
     * @var Collection<int, TScore>
     */
    #[ORM\OneToMany(targetEntity: TScore::class, mappedBy: 'latEquipe')]
    private Collection $lestScores;

    /**
     * @var Collection<int, TCompetition>
     */
    #[ORM\ManyToMany(targetEntity: TCompetition::class, mappedBy: 'teams')]
    private Collection $competitions;

    public function __construct()
    {
        $this->lesUsers = new ArrayCollection();
        $this->lestScores = new ArrayCollection();
        $this->competitions = new ArrayCollection();
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

    /**
     * @return Collection<int, User>
     */
    public function getLesUsers(): Collection
    {
        return $this->lesUsers;
    }

    public function addLesUser(User $lesUser): static
    {
        if (!$this->lesUsers->contains($lesUser)) {
            $this->lesUsers->add($lesUser);
            $lesUser->setLatEquipe($this);
        }

        return $this;
    }

    public function removeLesUser(User $lesUser): static
    {
        if ($this->lesUsers->removeElement($lesUser)) {
            // set the owning side to null (unless already changed)
            if ($lesUser->getLatEquipe() === $this) {
                $lesUser->setLatEquipe(null);
            }
        }

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
            $lestScore->setLatEquipe($this);
        }

        return $this;
    }

    public function removeLestScore(TScore $lestScore): static
    {
        if ($this->lestScores->removeElement($lestScore)) {
            // set the owning side to null (unless already changed)
            if ($lestScore->getLatEquipe() === $this) {
                $lestScore->setLatEquipe(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, TCompetition>
     */
    public function getCompetitions(): Collection
    {
        return $this->competitions;
    }

    public function addCompetition(TCompetition $competition): static
    {
        if (!$this->competitions->contains($competition)) {
            $this->competitions->add($competition);
        }

        return $this;
    }

    public function removeCompetition(TCompetition $competition): static
    {
        $this->competitions->removeElement($competition);

        return $this;
    }
}
