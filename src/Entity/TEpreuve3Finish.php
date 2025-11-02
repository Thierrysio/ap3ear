<?php

namespace App\Entity;

use App\Repository\TEpreuve3FinishRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TEpreuve3FinishRepository::class)]
class TEpreuve3Finish
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $equipeId = null;

    #[ORM\Column]
    private ?int $epreuveId = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getEpreuveId(): ?int
    {
        return $this->epreuveId;
    }

    public function setEpreuveId(int $epreuveId): static
    {
        $this->epreuveId = $epreuveId;

        return $this;
    }
}
