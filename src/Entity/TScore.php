<?php

namespace App\Entity;

use App\Repository\TScoreRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TScoreRepository::class)]
class TScore
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $note = null;

    #[ORM\ManyToOne(inversedBy: 'lestScores')]
    private ?TEpreuve $latEpreuve = null;

    #[ORM\ManyToOne(inversedBy: 'lestScores')]
    private ?TEquipe $latEquipe = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNote(): ?int
    {
        return $this->note;
    }

    public function setNote(int $note): static
    {
        $this->note = $note;

        return $this;
    }

    public function getLatEpreuve(): ?TEpreuve
    {
        return $this->latEpreuve;
    }

    public function setLatEpreuve(?TEpreuve $latEpreuve): static
    {
        $this->latEpreuve = $latEpreuve;

        return $this;
    }

    public function getLatEquipe(): ?TEquipe
    {
        return $this->latEquipe;
    }

    public function setLatEquipe(?TEquipe $latEquipe): static
    {
        $this->latEquipe = $latEquipe;

        return $this;
    }
}
