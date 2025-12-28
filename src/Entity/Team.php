<?php

namespace App\Entity;

use App\Repository\TeamRepository;
use App\Utils\UuidGenerator;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TeamRepository::class)]
class Team
{
    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_ELIMINATED = 'ELIMINATED';

    #[ORM\Id]
    #[ORM\Column(type: 'guid', unique: true)]
    private string $id;

    #[ORM\ManyToOne(inversedBy: 'teams')]
    #[ORM\JoinColumn(nullable: false)]
    private ?GameSession $gameSession = null;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column]
    private int $membersCount;

    #[ORM\Column]
    private int $evCurrent;

    #[ORM\Column(length: 16)]
    private string $status = self::STATUS_ACTIVE;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $deviceId = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    /**
     * @var Collection<int, QrScan>
     */
    #[ORM\OneToMany(mappedBy: 'team', targetEntity: QrScan::class, orphanRemoval: true)]
    private Collection $qrScans;

    /**
     * @var Collection<int, FuturePick>
     */
    #[ORM\OneToMany(mappedBy: 'team', targetEntity: FuturePick::class, orphanRemoval: true)]
    private Collection $futurePicks;

    /**
     * @var Collection<int, AppliedEffect>
     */
    #[ORM\OneToMany(mappedBy: 'team', targetEntity: AppliedEffect::class, orphanRemoval: true)]
    private Collection $appliedEffects;

    public function __construct()
    {
        $this->id = UuidGenerator::generate();
        $this->createdAt = new DateTimeImmutable();
        $this->qrScans = new ArrayCollection();
        $this->futurePicks = new ArrayCollection();
        $this->appliedEffects = new ArrayCollection();
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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getMembersCount(): int
    {
        return $this->membersCount;
    }

    public function setMembersCount(int $membersCount): self
    {
        $this->membersCount = $membersCount;

        return $this;
    }

    public function getEvCurrent(): int
    {
        return $this->evCurrent;
    }

    public function setEvCurrent(int $evCurrent): self
    {
        $this->evCurrent = $evCurrent;

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

    public function getDeviceId(): ?string
    {
        return $this->deviceId;
    }

    public function setDeviceId(?string $deviceId): self
    {
        $this->deviceId = $deviceId;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
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
            $qrScan->setTeam($this);
        }

        return $this;
    }

    public function removeQrScan(QrScan $qrScan): self
    {
        if ($this->qrScans->removeElement($qrScan)) {
            if ($qrScan->getTeam() === $this) {
                $qrScan->setTeam(null);
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
            $futurePick->setTeam($this);
        }

        return $this;
    }

    public function removeFuturePick(FuturePick $futurePick): self
    {
        if ($this->futurePicks->removeElement($futurePick)) {
            if ($futurePick->getTeam() === $this) {
                $futurePick->setTeam(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, AppliedEffect>
     */
    public function getAppliedEffects(): Collection
    {
        return $this->appliedEffects;
    }

    public function addAppliedEffect(AppliedEffect $appliedEffect): self
    {
        if (!$this->appliedEffects->contains($appliedEffect)) {
            $this->appliedEffects->add($appliedEffect);
            $appliedEffect->setTeam($this);
        }

        return $this;
    }

    public function removeAppliedEffect(AppliedEffect $appliedEffect): self
    {
        if ($this->appliedEffects->removeElement($appliedEffect)) {
            if ($appliedEffect->getTeam() === $this) {
                $appliedEffect->setTeam(null);
            }
        }

        return $this;
    }
}
