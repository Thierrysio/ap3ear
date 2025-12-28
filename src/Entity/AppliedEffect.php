<?php

namespace App\Entity;

use App\Repository\AppliedEffectRepository;
use App\Utils\UuidGenerator;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AppliedEffectRepository::class)]
class AppliedEffect
{
    public const SOURCE_FUTURE_PICK = 'FUTURE_PICK';
    public const SOURCE_PENALTY = 'PENALTY';
    public const SOURCE_ADMIN = 'ADMIN';

    public const EFFECT_EV_ADD = 'EV_ADD';
    public const EFFECT_EV_SUB = 'EV_SUB';
    public const EFFECT_IMMUNITY = 'IMMUNITY';
    public const EFFECT_REVEAL = 'REVEAL';
    public const EFFECT_NEXT_ROUND_MOD = 'NEXT_ROUND_MOD';

    #[ORM\Id]
    #[ORM\Column(type: 'guid', unique: true)]
    private string $id;

    #[ORM\ManyToOne(inversedBy: 'appliedEffects')]
    #[ORM\JoinColumn(nullable: false)]
    private ?GameSession $gameSession = null;

    #[ORM\ManyToOne]
    private ?Round $round = null;

    #[ORM\ManyToOne(inversedBy: 'appliedEffects')]
    private ?Team $team = null;

    #[ORM\Column(length: 20)]
    private string $sourceType;

    #[ORM\Column(length: 32)]
    private string $effectType;

    #[ORM\Column(type: 'json')]
    private array $payload = [];

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $appliedAt;

    #[ORM\Column(nullable: true)]
    private ?int $scheduledRoundIndex = null;

    public function __construct()
    {
        $this->id = UuidGenerator::generate();
        $this->appliedAt = new DateTimeImmutable();
        $this->sourceType = self::SOURCE_FUTURE_PICK;
        $this->effectType = self::EFFECT_EV_ADD;
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

    public function getSourceType(): string
    {
        return $this->sourceType;
    }

    public function setSourceType(string $sourceType): self
    {
        $this->sourceType = $sourceType;

        return $this;
    }

    public function getEffectType(): string
    {
        return $this->effectType;
    }

    public function setEffectType(string $effectType): self
    {
        $this->effectType = $effectType;

        return $this;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function setPayload(array $payload): self
    {
        $this->payload = $payload;

        return $this;
    }

    public function getAppliedAt(): DateTimeImmutable
    {
        return $this->appliedAt;
    }

    public function setAppliedAt(DateTimeImmutable $appliedAt): self
    {
        $this->appliedAt = $appliedAt;

        return $this;
    }

    public function getScheduledRoundIndex(): ?int
    {
        return $this->scheduledRoundIndex;
    }

    public function setScheduledRoundIndex(?int $scheduledRoundIndex): self
    {
        $this->scheduledRoundIndex = $scheduledRoundIndex;

        return $this;
    }
}
