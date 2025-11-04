<?php

namespace App\Entity;

use App\Repository\Game4GameRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: Game4GameRepository::class)]
class Game4Game
{
    // Phases possibles
    public const PHASE_SETUP   = 'setup';   // nouvelle phase : préparation / pioche
    public const PHASE_LOBBY   = 'lobby';
    public const PHASE_RUNNING = 'running';
    public const PHASE_FINISHED = 'finished';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // Phase globale du jeu
    #[ORM\Column(length: 16)]
    private string $phase = self::PHASE_SETUP;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    /**
     * Date/heure de fin de la **manche courante**.
     */
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $endsAt = null;

    // Compteurs existants
    #[ORM\Column(options: ['default' => 0])]
    private int $drawCount = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $discardCount = 0;

    // ======== Rounds ========
    /**
     * Numéro de la manche en cours (1-based). 0 = pas commencé.
     */
    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $roundIndex = 0;

    /**
     * Nombre total de manches prévues.
     */
    #[ORM\Column(type: 'integer', options: ['default' => 20])]
    private int $roundsMax = 20;

    /**
     * Durée d’une manche en secondes (ex: 60).
     */
    #[ORM\Column(type: 'integer', options: ['default' => 60])]
    private int $roundSeconds = 60;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    // ======== Getters/Setters de base ========
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPhase(): string
    {
        return $this->phase;
    }

    public function setPhase(string $phase): self
    {
        $this->phase = $phase;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getEndsAt(): ?\DateTimeImmutable
    {
        return $this->endsAt;
    }

    public function setEndsAt(?\DateTimeImmutable $endsAt): self
    {
        $this->endsAt = $endsAt;
        return $this;
    }

    public function getDrawCount(): int
    {
        return $this->drawCount;
    }

    public function incDrawCount(): self
    {
        $this->drawCount++;
        return $this;
    }

    public function getDiscardCount(): int
    {
        return $this->discardCount;
    }

    public function incDiscardCount(): self
    {
        $this->discardCount++;
        return $this;
    }

    // ======== Rounds ========
    public function getRoundIndex(): int
    {
        return $this->roundIndex;
    }

    public function setRoundIndex(int $roundIndex): self
    {
        $this->roundIndex = max(0, $roundIndex);
        return $this;
    }

    public function getRoundsMax(): int
    {
        return $this->roundsMax;
    }

    public function setRoundsMax(int $roundsMax): self
    {
        $this->roundsMax = max(1, $roundsMax);
        return $this;
    }

    public function getRoundSeconds(): int
    {
        return $this->roundSeconds;
    }

    public function setRoundSeconds(int $roundSeconds): self
    {
        $this->roundSeconds = max(1, $roundSeconds);
        return $this;
    }

    // ======== Helpers pratiques ========

    /** Le jeu est-il en phase RUNNING ? */
    public function isRunning(): bool
    {
        return $this->phase === self::PHASE_RUNNING;
    }

    /** Le jeu est-il en phase SETUP (pioche autorisée) ? */
    public function isSetup(): bool
    {
        return $this->phase === self::PHASE_SETUP;
    }

    /** Lance la toute première manche (index = 1) et positionne endsAt. */
    public function startFirstRound(?\DateTimeImmutable $now = null): self
    {
        $now = $now ?? new \DateTimeImmutable();
        $this->phase = self::PHASE_RUNNING;
        $this->roundIndex = 1;
        $this->endsAt = $now->modify('+' . $this->roundSeconds . ' seconds');
        return $this;
    }

    /**
     * Passe à la manche suivante si possible.
     * Retourne true si on a bien avancé, false si on était déjà à la dernière.
     */
    public function nextRound(?\DateTimeImmutable $now = null): bool
    {
        if ($this->roundIndex >= $this->roundsMax) {
            $this->phase = self::PHASE_FINISHED;
            return false;
        }
        $now = $now ?? new \DateTimeImmutable();
        $this->roundIndex++;
        $this->endsAt = $now->modify('+' . $this->roundSeconds . ' seconds');
        return true;
    }

    /** Rallonge la manche courante de N secondes. */
    public function extendSeconds(int $extraSeconds): self
    {
        if ($this->endsAt === null) {
            return $this;
        }
        if ($extraSeconds <= 0) {
            return $this;
        }
        $this->endsAt = $this->endsAt->modify('+' . $extraSeconds . ' seconds');
        return $this;
    }

    /** Secondes restantes sur la manche courante (>=0). */
    public function secondsLeft(?\DateTimeImmutable $now = null): int
    {
        if ($this->endsAt === null) {
            return 0;
        }
        $now = $now ?? new \DateTimeImmutable();
        return max(0, $this->endsAt->getTimestamp() - $now->getTimestamp());
    }

    /** Réinitialise le timer de la manche courante à roundSeconds à partir de now. */
    public function resetRoundTimer(?\DateTimeImmutable $now = null): self
    {
        $now = $now ?? new \DateTimeImmutable();
        $this->endsAt = $now->modify('+' . $this->roundSeconds . ' seconds');
        return $this;
    }
}
