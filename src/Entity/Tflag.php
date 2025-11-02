<?php

namespace App\Entity;

use App\Repository\TflagRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TflagRepository::class)]
class Tflag
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?float $latitude = null;

    #[ORM\Column]
    private ?float $longitude = null;

    #[ORM\Column(length: 255)]
    private ?string $indication = null;

    #[ORM\Column]
    private ?int $timeoutSec = null;

    #[ORM\Column]
    private ?int $ordre = null;

    #[ORM\ManyToOne(inversedBy: 'epreuve_id')]
    private ?TEpreuve $epreuve = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLatitude(): ?float
    {
        return $this->latitude;
    }

    public function setLatitude(float $latitude): static
    {
        $this->latitude = $latitude;

        return $this;
    }

    public function getLongitude(): ?float
    {
        return $this->longitude;
    }

    public function setLongitude(float $longitude): static
    {
        $this->longitude = $longitude;

        return $this;
    }

    public function getIndication(): ?string
    {
        return $this->indication;
    }

    public function setIndication(string $indication): static
    {
        $this->indication = $indication;

        return $this;
    }

    public function getTimeoutSec(): ?int
    {
        return $this->timeoutSec;
    }

    public function setTimeoutSec(int $timeoutSec): static
    {
        $this->timeoutSec = $timeoutSec;

        return $this;
    }

    public function getOrdre(): ?int
    {
        return $this->ordre;
    }

    public function setOrdre(int $ordre): static
    {
        $this->ordre = $ordre;

        return $this;
    }

    public function getEpreuve(): ?TEpreuve
    {
        return $this->epreuve;
    }

    public function setEpreuve(?TEpreuve $epreuve): static
    {
        $this->epreuve = $epreuve;

        return $this;
    }
}
