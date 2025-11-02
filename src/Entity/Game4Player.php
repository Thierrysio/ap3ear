<?php

namespace App\Entity;

use App\Repository\Game4PlayerRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: Game4PlayerRepository::class)]
#[ORM\Table(name: 'game4_player')]
# Unicité d’un joueur (équipe) dans un game
#[ORM\UniqueConstraint(name: 'uniq_game_equipe', columns: ['game_id', 'equipe_id'])]
# Index perfs pour tes requêtes
#[ORM\Index(name: 'idx_g4player_game_alive', columns: ['game_id','is_alive'])]
#[ORM\Index(name: 'idx_g4player_game_role', columns: ['game_id','role'])]
#[ORM\Index(name: 'idx_g4player_game_locked', columns: ['game_id','locked_in_duel'])]
class Game4Player
{
    public const ROLE_HUMAN  = 'human';
    public const ROLE_ZOMBIE = 'zombie';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Game4Game::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Game4Game $game;

    #[ORM\Column(type: 'integer')]
    private int $equipeId;

    #[ORM\Column(type: 'string', length: 100)]
    private string $name;

    #[ORM\Column(type: 'string', length: 12)]
    #[Assert\Choice(choices: [self::ROLE_HUMAN, self::ROLE_ZOMBIE])]
    private string $role = self::ROLE_HUMAN;

    #[ORM\Column(type: 'integer', options: ['default' => 3])]
    private int $lives = 3;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $isAlive = true;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $lockedInDuel = false;

    #[ORM\ManyToOne(targetEntity: Game4Duel::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Game4Duel $incomingDuel = null;

    public function getId(): ?int { return $this->id; }

    public function getGame(): Game4Game { return $this->game; }
    public function setGame(Game4Game $g): self { $this->game = $g; return $this; }

    public function getEquipeId(): int { return $this->equipeId; }
    public function setEquipeId(int $id): self { $this->equipeId = $id; return $this; }

    public function getName(): string { return $this->name; }
    public function setName(string $n): self { $this->name = $n; return $this; }

    public function getRole(): string { return $this->role; }
    public function setRole(string $r): self { $this->role = $r; return $this; }

    public function getLives(): int { return $this->lives; }
    public function setLives(int $v): self { $this->lives = $v; $this->isAlive = $v > 0; return $this; }
    public function decLife(): self { $this->lives = max(0, $this->lives - 1); $this->isAlive = $this->lives > 0; return $this; }
    public function incLife(): self { $this->lives++; return $this; }

    public function isAlive(): bool { return $this->isAlive; }
    public function setIsAlive(bool $b): self { $this->isAlive = $b; return $this; }

    public function isLockedInDuel(): bool { return $this->lockedInDuel; }
    public function setLockedInDuel(bool $b): self { $this->lockedInDuel = $b; return $this; }

    public function getIncomingDuel(): ?Game4Duel { return $this->incomingDuel; }
    public function setIncomingDuel(?Game4Duel $d): self { $this->incomingDuel = $d; return $this; }

    // Helpers pratiques
    public function lockForDuel(?Game4Duel $duel): self
    {
        $this->lockedInDuel = true;
        $this->incomingDuel = $duel;
        return $this;
    }

    public function unlockFromDuel(): self
    {
        $this->lockedInDuel = false;
        $this->incomingDuel = null;
        return $this;
    }

    public function becomeZombie(): self
    {
        $this->role = self::ROLE_ZOMBIE;
        return $this;
    }

    public function becomeHuman(): self
    {
        $this->role = self::ROLE_HUMAN;
        return $this;
    }
}
