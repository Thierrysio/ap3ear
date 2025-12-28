<?php

namespace App\Entity;

use App\Repository\FuturePickRepository;
use App\Utils\UuidGenerator;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FuturePickRepository::class)]
class FuturePick
{
    public const MODE_MANUAL = 'MANUAL';
    public const MODE_AUTO_TIMEOUT = 'AUTO_TIMEOUT';
    public const MODE_AUTO_LATE = 'AUTO_LATE';
    public const MODE_ADMIN_ASSIGNED = 'ADMIN_ASSIGNED';

    #[ORM\Id]
    #[ORM\Column(type: 'guid', unique: true)]
    private string $id;

    #[ORM\ManyToOne(inversedBy: 'futurePicks')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Round $round = null;

    #[ORM\ManyToOne(inversedBy: 'futurePicks')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Team $team = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?FutureOption $futureOption = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $pickedAt;

    #[ORM\Column(length: 20)]
    private string $pickMode = self::MODE_MANUAL;

    #[ORM\Column]
    private bool $locked = true;

    public function __construct()
    {
        $this->id = UuidGenerator::generate();
        $this->pickedAt = new DateTimeImmutable();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getRound(): ?Round
    {
        return $this->round;
    }

    public function setRound(?Round $round): self
    {
        $this->round = $round;

        return $this;
    }

    public function getTeam(): ?Team
    {
        return $this->team;
    }

    public function setTeam(?Team $team): self
    {
        $this->team = $team;

        return $this;
    }

    public function getFutureOption(): ?FutureOption
    {
        return $this->futureOption;
    }

    public function setFutureOption(?FutureOption $futureOption): self
    {
        $this->futureOption = $futureOption;

        return $this;
    }

    public function getPickedAt(): DateTimeImmutable
    {
        return $this->pickedAt;
    }

    public function setPickedAt(DateTimeImmutable $pickedAt): self
    {
        $this->pickedAt = $pickedAt;

        return $this;
    }

    public function getPickMode(): string
    {
        return $this->pickMode;
    }

    public function setPickMode(string $pickMode): self
    {
        $this->pickMode = $pickMode;

        return $this;
    }

    public function isLocked(): bool
    {
        return $this->locked;
    }

    public function setLocked(bool $locked): self
    {
        $this->locked = $locked;

        return $this;
    }
}
