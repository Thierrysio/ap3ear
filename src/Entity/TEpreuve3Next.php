<?php

namespace App\Entity;

use App\Repository\TEpreuve3NextRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TEpreuve3NextRepository::class)]
class TEpreuve3Next
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?float $lat = null;

    #[ORM\Column]
    private ?float $lon = null;

    #[ORM\Column(length: 255)]
    private ?string $hint = null;

    #[ORM\Column]
    private ?int $timeOutSec = null;

    #[ORM\Column]
    private ?bool $valid = null;

    #[ORM\Column(length: 255)]
    private ?string $scannedTokenHash = null;

    #[ORM\Column]
    private ?int $flagId = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLat(): ?float
    {
        return $this->lat;
    }

    public function setLat(float $lat): static
    {
        $this->lat = $lat;

        return $this;
    }

    public function getLon(): ?float
    {
        return $this->lon;
    }

    public function setLon(float $lon): static
    {
        $this->lon = $lon;

        return $this;
    }

    public function getHint(): ?string
    {
        return $this->hint;
    }

    public function setHint(string $hint): static
    {
        $this->hint = $hint;

        return $this;
    }

    public function getTimeOutSec(): ?int
    {
        return $this->timeOutSec;
    }

    public function setTimeOutSec(int $timeOutSec): static
    {
        $this->timeOutSec = $timeOutSec;

        return $this;
    }

    public function isValid(): ?bool
    {
        return $this->valid;
    }

    public function setValid(bool $valid): static
    {
        $this->valid = $valid;

        return $this;
    }

    public function getScannedTokenHash(): ?string
    {
        return $this->scannedTokenHash;
    }

    public function setScannedTokenHash(string $scannedTokenHash): static
    {
        $this->scannedTokenHash = $scannedTokenHash;

        return $this;
    }

    public function getFlagId(): ?int
    {
        return $this->flagId;
    }

    public function setFlagId(int $flagId): static
    {
        $this->flagId = $flagId;

        return $this;
    }
}
