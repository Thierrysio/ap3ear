<?php

namespace App\Entity;

use App\Repository\FutureOptionRepository;
use App\Utils\UuidGenerator;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FutureOptionRepository::class)]
class FutureOption
{
    public const RISK_GREEN = 'GREEN';
    public const RISK_YELLOW = 'YELLOW';
    public const RISK_RED = 'RED';

    #[ORM\Id]
    #[ORM\Column(type: 'guid', unique: true)]
    private string $id;

    #[ORM\ManyToOne(inversedBy: 'futureOptions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Round $round = null;

    #[ORM\Column(length: 10)]
    private string $code;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(length: 16)]
    private string $riskLevel;

    #[ORM\Column(type: 'json')]
    private array $immediateEffects = [];

    #[ORM\Column(type: 'json')]
    private array $delayedEffects = [];

    #[ORM\Column]
    private int $displayOrder = 0;

    public function __construct()
    {
        $this->id = UuidGenerator::generate();
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

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = $code;

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

    public function getRiskLevel(): string
    {
        return $this->riskLevel;
    }

    public function setRiskLevel(string $riskLevel): self
    {
        $this->riskLevel = $riskLevel;

        return $this;
    }

    public function getImmediateEffects(): array
    {
        return $this->immediateEffects;
    }

    public function setImmediateEffects(array $immediateEffects): self
    {
        $this->immediateEffects = $immediateEffects;

        return $this;
    }

    public function getDelayedEffects(): array
    {
        return $this->delayedEffects;
    }

    public function setDelayedEffects(array $delayedEffects): self
    {
        $this->delayedEffects = $delayedEffects;

        return $this;
    }

    public function getDisplayOrder(): int
    {
        return $this->displayOrder;
    }

    public function setDisplayOrder(int $displayOrder): self
    {
        $this->displayOrder = $displayOrder;

        return $this;
    }
}
