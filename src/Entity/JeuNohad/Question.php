<?php

namespace App\Entity\JeuNohad;

use App\Repository\JeuNohad\QuestionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: QuestionRepository::class)]
class Question
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'questions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Quiz $quiz = null;

    #[ORM\Column(length: 255)]
    private string $enonce;

    #[ORM\Column]
    private int $dureeSecondes;

    /** @var Collection<int, Choice> */
    #[ORM\OneToMany(mappedBy: 'question', targetEntity: Choice::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $choices;

    /** @var Collection<int, UserResponse> */
    #[ORM\OneToMany(mappedBy: 'question', targetEntity: UserResponse::class, cascade: ['remove'])]
    private Collection $responses;

    public function __construct()
    {
        $this->choices = new ArrayCollection();
        $this->responses = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQuiz(): ?Quiz
    {
        return $this->quiz;
    }

    public function setQuiz(?Quiz $quiz): self
    {
        $this->quiz = $quiz;

        return $this;
    }

    public function getEnonce(): string
    {
        return $this->enonce;
    }

    public function setEnonce(string $enonce): self
    {
        $this->enonce = $enonce;

        return $this;
    }

    public function getDureeSecondes(): int
    {
        return $this->dureeSecondes;
    }

    public function setDureeSecondes(int $dureeSecondes): self
    {
        $this->dureeSecondes = $dureeSecondes;

        return $this;
    }

    /**
     * @return Collection<int, Choice>
     */
    public function getChoices(): Collection
    {
        return $this->choices;
    }

    public function addChoice(Choice $choice): self
    {
        if (!$this->choices->contains($choice)) {
            $this->choices->add($choice);
            $choice->setQuestion($this);
        }

        return $this;
    }

    public function removeChoice(Choice $choice): self
    {
        if ($this->choices->removeElement($choice)) {
            if ($choice->getQuestion() === $this) {
                $choice->setQuestion(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, UserResponse>
     */
    public function getResponses(): Collection
    {
        return $this->responses;
    }
}
