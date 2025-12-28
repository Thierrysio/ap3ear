<?php

namespace App\Entity;

use App\Repository\RoundRepository;
use App\Utils\UuidGenerator;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RoundRepository::class)]
#[ORM\Table(name: 'rounds')]
class Round
{
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_CLOSED = 'CLOSED';

    #[ORM\Id]
    #[ORM\Column(type: 'guid', unique: true)]
    private string $id;

    #[ORM\ManyToOne(inversedBy: 'rounds')]
    #[ORM\JoinColumn(nullable: false)]
    private ?GameSession $gameSession = null;

    #[ORM\Column(name: 'round_index')]
    private int $index;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(type: 'json')]
    private array $physicalChallenge = [];

    #[ORM\Column(length: 255)]
    private string $qrPayloadExpected;

    #[ORM\Column]
    private int $timeLimitSec;

    #[ORM\Column(length: 16)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $startAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $endAt = null;

    /**
     * @var Collection<int, FutureOption>
     */
    #[ORM\OneToMany(mappedBy: 'round', targetEntity: FutureOption::class, orphanRemoval: true)]
    #[ORM\OrderBy(['displayOrder' => 'ASC'])]
    private Collection $futureOptions;

    /**
     * @var Collection<int, QrScan>
     */
    #[ORM\OneToMany(mappedBy: 'round', targetEntity: QrScan::class, orphanRemoval: true)]
    private Collection $qrScans;

    /**
     * @var Collection<int, FuturePick>
     */
    #[ORM\OneToMany(mappedBy: 'round', targetEntity: FuturePick::class, orphanRemoval: true)]
    private Collection $futurePicks;

    public function __construct()
    {
        $this->id = UuidGenerator::generate();
        $this->futureOptions = new ArrayCollection();
        $this->qrScans = new ArrayCollection();
        $this->futurePicks = new ArrayCollection();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getGameSession(): ?GameSession
    {
        return $this->gameSession;
    }

    public function setGameSession(?GameSession $gameSession): self
    {
        $this->gameSession = $gameSession;

        return $this;
    }

    public function getIndex(): int
    {
        return $this->index;
    }

    public function setIndex(int $index): self
    {
        $this->index = $index;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getPhysicalChallenge(): array
    {
        return $this->physicalChallenge;
    }

    public function setPhysicalChallenge(array $physicalChallenge): self
    {
        $this->physicalChallenge = $physicalChallenge;

        return $this;
    }

    public function getQrPayloadExpected(): string
    {
        return $this->qrPayloadExpected;
    }

    public function setQrPayloadExpected(string $qrPayloadExpected): self
    {
        $this->qrPayloadExpected = $qrPayloadExpected;

        return $this;
    }

    public function getTimeLimitSec(): int
    {
        return $this->timeLimitSec;
    }

    public function setTimeLimitSec(int $timeLimitSec): self
    {
        $this->timeLimitSec = $timeLimitSec;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getStartAt(): ?DateTimeImmutable
    {
        return $this->startAt;
    }

    public function setStartAt(?DateTimeImmutable $startAt): self
    {
        $this->startAt = $startAt;

        return $this;
    }

    public function getEndAt(): ?DateTimeImmutable
    {
        return $this->endAt;
    }

    public function setEndAt(?DateTimeImmutable $endAt): self
    {
        $this->endAt = $endAt;

        return $this;
    }

    /**
     * @return Collection<int, FutureOption>
     */
    public function getFutureOptions(): Collection
    {
        return $this->futureOptions;
    }

    public function addFutureOption(FutureOption $futureOption): self
    {
        if (!$this->futureOptions->contains($futureOption)) {
            $this->futureOptions->add($futureOption);
            $futureOption->setRound($this);
        }

        return $this;
    }

    public function removeFutureOption(FutureOption $futureOption): self
    {
        if ($this->futureOptions->removeElement($futureOption)) {
            if ($futureOption->getRound() === $this) {
                $futureOption->setRound(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, QrScan>
     */
    public function getQrScans(): Collection
    {
        return $this->qrScans;
    }

    public function addQrScan(QrScan $qrScan): self
    {
        if (!$this->qrScans->contains($qrScan)) {
            $this->qrScans->add($qrScan);
            $qrScan->setRound($this);
        }

        return $this;
    }

    public function removeQrScan(QrScan $qrScan): self
    {
        if ($this->qrScans->removeElement($qrScan)) {
            if ($qrScan->getRound() === $this) {
                $qrScan->setRound(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, FuturePick>
     */
    public function getFuturePicks(): Collection
    {
        return $this->futurePicks;
    }

    public function addFuturePick(FuturePick $futurePick): self
    {
        if (!$this->futurePicks->contains($futurePick)) {
            $this->futurePicks->add($futurePick);
            $futurePick->setRound($this);
        }

        return $this;
    }

    public function removeFuturePick(FuturePick $futurePick): self
    {
        if ($this->futurePicks->removeElement($futurePick)) {
            if ($futurePick->getRound() === $this) {
                $futurePick->setRound(null);
            }
        }

        return $this;
    }
}
