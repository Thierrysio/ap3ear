<?php

namespace App\Entity;

use App\Repository\SetChoixBontoDtoRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SetChoixBontoDtoRepository::class)]
class SetChoixBontoDto
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $Manche = null;

    #[ORM\Column]
    private ?int $EquipeId = null;

    #[ORM\Column]
    private ?int $ChoixIndex = null;

    #[ORM\Column(length: 255)]
    private ?string $gagnant = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getManche(): ?int
    {
        return $this->Manche;
    }

    public function setManche(int $Manche): static
    {
        $this->Manche = $Manche;

        return $this;
    }

    public function getEquipeId(): ?int
    {
        return $this->EquipeId;
    }

    public function setEquipeId(int $EquipeId): static
    {
        $this->EquipeId = $EquipeId;

        return $this;
    }

    public function getChoixIndex(): ?int
    {
        return $this->ChoixIndex;
    }

    public function setChoixIndex(int $ChoixIndex): static
    {
        $this->ChoixIndex = $ChoixIndex;

        return $this;
    }

    public function getGagnant(): ?string
    {
        return $this->gagnant;
    }

    public function setGagnant(string $gagnant): static
    {
        $this->gagnant = $gagnant;

        return $this;
    }
}
