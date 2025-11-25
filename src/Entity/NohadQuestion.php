<?php

namespace App\Entity;

use App\Repository\NohadQuestionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NohadQuestionRepository::class)]
class NohadQuestion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $enonce = null;

    #[ORM\Column]
    private ?int $dureeSecondes = null;

    /**
     * @var Collection<int, NohadReponse>
     */
    #[ORM\OneToMany(targetEntity: NohadReponse::class, mappedBy: 'question', orphanRemoval: true)]
    private Collection $reponses;

    public function __construct()
    {
        $this->reponses = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEnonce(): ?string
    {
        return $this->enonce;
    }

    public function setEnonce(string $enonce): static
    {
        $this->enonce = $enonce;

        return $this;
    }

    public function getDureeSecondes(): ?int
    {
        return $this->dureeSecondes;
    }

    public function setDureeSecondes(int $dureeSecondes): static
    {
        $this->dureeSecondes = $dureeSecondes;

        return $this;
    }

    /**
     * @return Collection<int, NohadReponse>
     */
    public function getReponses(): Collection
    {
        return $this->reponses;
    }

    public function addReponse(NohadReponse $reponse): static
    {
        if (!$this->reponses->contains($reponse)) {
            $this->reponses->add($reponse);
            $reponse->setQuestion($this);
        }

        return $this;
    }

    public function removeReponse(NohadReponse $reponse): static
    {
        if ($this->reponses->removeElement($reponse)) {
            // set the owning side to null (unless already changed)
            if ($reponse->getQuestion() === $this) {
                $reponse->setQuestion(null);
            }
        }

        return $this;
    }
}
