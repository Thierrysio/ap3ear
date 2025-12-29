<?php

namespace App\Entity;

use App\Repository\GameSessionRepository;
use App\Utils\UuidGenerator;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GameSessionRepository::class)]
class GameSession
{
    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_RUNNING = 'RUNNING';
    public const STATUS_FINISHED = 'FINISHED';
    public const STATUS_CANCELLED = 'CANCELLED';

    #[ORM\Id]
    #[ORM\Column(type: 'guid', unique: true)]
    private string $id;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(length: 32, unique: true)]
    private string $gameCode;

    #[ORM\Column(length: 16)]
    private string $status = self::STATUS_DRAFT;

    #[ORM\Column(options: ['default' => 10])]
    private int $maxTeams = 10;

    #[ORM\Column(options: ['default' => 15])]
    private int $evStart = 15;

    #[ORM\Column(options: ['default' => 15])]
    private int $choiceTimeoutSec = 15;

    #[ORM\Column]
    private bool $gpsEnabled = false;

    #[ORM\Column(nullable: true)]
    private ?int $gpsRadiusMeters = null;

    #[ORM\Column(options: ['default' => 1])]
    private int $currentRoundIndex = 1;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $startedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $finishedAt = null;

    #[ORM\Column(type: 'json')]
    private array $settings = [];

    #[ORM\Column(nullable: true)]
    private ?int $tepreuveId = null;

    /**
     * @var Collection<int, Team>
     */
    #[ORM\OneToMany(mappedBy: 'gameSession', targetEntity: Team::class, orphanRemoval: true)]
    private Collection $teams;

    /**
     * @var Collection<int, Round>
     */
    #[ORM\OneToMany(mappedBy: 'gameSession', targetEntity: Round::class, orphanRemoval: true)]
    #[ORM\OrderBy(['index' => 'ASC'])]
    private Collection $rounds;

    public function __construct()
    {
        $this->id = UuidGenerator::generate();
        $this->createdAt = new DateTimeImmutable();
        $this->teams = new ArrayCollection();
        $this->rounds = new ArrayCollection();
    }

    public function getId(): string
    {
        return $this->id;
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

    public function getGameCode(): string
    {
        return $this->gameCode;
    }

    public function setGameCode(string $gameCode): self
    {
        $this->gameCode = $gameCode;

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

    public function getMaxTeams(): int
    {
        return $this->maxTeams;
    }

    public function setMaxTeams(int $maxTeams): self
    {
        $this->maxTeams = $maxTeams;

        return $this;
    }

    public function getEvStart(): int
    {
        return $this->evStart;
    }

    public function setEvStart(int $evStart): self
    {
        $this->evStart = $evStart;

        return $this;
    }

    public function getChoiceTimeoutSec(): int
    {
        return $this->choiceTimeoutSec;
    }

    public function setChoiceTimeoutSec(int $choiceTimeoutSec): self
    {
        $this->choiceTimeoutSec = $choiceTimeoutSec;

        return $this;
    }

    public function isGpsEnabled(): bool
    {
        return $this->gpsEnabled;
    }

    public function setGpsEnabled(bool $gpsEnabled): self
    {
        $this->gpsEnabled = $gpsEnabled;

        return $this;
    }

    public function getGpsRadiusMeters(): ?int
    {
        return $this->gpsRadiusMeters;
    }

    public function setGpsRadiusMeters(?int $gpsRadiusMeters): self
    {
        $this->gpsRadiusMeters = $gpsRadiusMeters;

        return $this;
    }

    public function getCurrentRoundIndex(): int
    {
        return $this->currentRoundIndex;
    }

    public function setCurrentRoundIndex(int $currentRoundIndex): self
    {
        $this->currentRoundIndex = $currentRoundIndex;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getStartedAt(): ?DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function setStartedAt(?DateTimeImmutable $startedAt): self
    {
        $this->startedAt = $startedAt;

        return $this;
    }

    public function getFinishedAt(): ?DateTimeImmutable
    {
        return $this->finishedAt;
    }

    public function setFinishedAt(?DateTimeImmutable $finishedAt): self
    {
        $this->finishedAt = $finishedAt;

        return $this;
    }

    public function getSettings(): array
    {
        return $this->settings;
    }

    public function setSettings(array $settings): self
    {
        $this->settings = $settings;

        return $this;
    }

    public function getTepreuveId(): ?int
    {
        return $this->tepreuveId;
    }

    public function setTepreuveId(?int $tepreuveId): self
    {
        $this->tepreuveId = $tepreuveId;

        return $this;
    }

    /**
     * @return Collection<int, Team>
     */
    public function getTeams(): Collection
    {
        return $this->teams;
    }

    public function addTeam(Team $team): self
    {
        if (!$this->teams->contains($team)) {
            $this->teams->add($team);
            $team->setGameSession($this);
        }

        return $this;
    }

    public function removeTeam(Team $team): self
    {
        if ($this->teams->removeElement($team)) {
            if ($team->getGameSession() === $this) {
                $team->setGameSession(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Round>
     */
    public function getRounds(): Collection
    {
        return $this->rounds;
    }

    public function addRound(Round $round): self
    {
        if (!$this->rounds->contains($round)) {
            $this->rounds->add($round);
            $round->setGameSession($this);
        }

        return $this;
    }

    public function removeRound(Round $round): self
    {
        if ($this->rounds->removeElement($round)) {
            if ($round->getGameSession() === $this) {
                $round->setGameSession(null);
            }
        }

        return $this;
    }
}
