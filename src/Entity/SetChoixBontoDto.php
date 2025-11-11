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
    private ?int $manche = null;

    #[ORM\Column]
    private ?int $equipeId = null;

    #[ORM\Column]
    private ?int $choixIndex = null;

    #[ORM\Column(length: 255)]
    private ?string $gagnant = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getManche(): ?int
    {
        return $this->manche;
    }

    public function setManche(int $manche): static
    {
        $this->manche = $manche;

        return $this;
    }

    public function getEquipeId(): ?int
    {
        return $this->equipeId;
    }

    public function setEquipeId(int $equipeId): static
    {
        $this->equipeId = $equipeId;

        return $this;
    }

    public function getChoixIndex(): ?int
    {
        return $this->choixIndex;
    }

    public function setChoixIndex(int $choixIndex): static
    {
        $this->choixIndex = $choixIndex;

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
