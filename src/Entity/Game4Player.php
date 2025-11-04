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
#[ORM\Index(name: 'idx_g4player_game_alive',  columns: ['game_id','is_alive'])]
#[ORM\Index(name: 'idx_g4player_game_role',   columns: ['game_id','role'])]
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
    #[ORM\JoinColumn(name: 'game_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Game4Game $game;

    #[ORM\Column(type: 'integer', name: 'equipe_id')]
    private int $equipeId;

    #[ORM\Column(type: 'string', length: 100, name: 'name')]
    private string $name;

    #[ORM\Column(type: 'string', length: 12, name: 'role')]
    #[Assert\Choice(choices: [self::ROLE_HUMAN, self::ROLE_ZOMBIE])]
    private string $role = self::ROLE_HUMAN;

    #[ORM\Column(type: 'integer', options: ['default' => 3], name: 'lives')]
    private int $lives = 3;

    #[ORM\Column(type: 'boolean', options: ['default' => true], name: 'is_alive')]
    private bool $isAlive = true;

    #[ORM\Column(type: 'boolean', options: ['default' => false], name: 'locked_in_duel')]
    private bool $lockedInDuel = false;

    // Nouveaux états persistants utilisés par les règles de duel
    #[ORM\Column(type: 'boolean', options: ['default' => false], name: 'is_zombie')]
    private bool $isZombie = false;

    #[ORM\Column(type: 'boolean', options: ['default' => false], name: 'is_eliminated')]
    private bool $isEliminated = false;

    #[ORM\ManyToOne(targetEntity: Game4Duel::class)]
    #[ORM\JoinColumn(name: 'incoming_duel_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Game4Duel $incomingDuel = null;

    // ===== Getters / Setters de base =====

    public function getId(): ?int { return $this->id; }

    public function getGame(): Game4Game { return $this->game; }
    public function setGame(Game4Game $g): self { $this->game = $g; return $this; }

    public function getEquipeId(): int { return $this->equipeId; }
    public function setEquipeId(int $id): self { $this->equipeId = $id; return $this; }

    public function getName(): string { return $this->name; }
    public function setName(string $n): self { $this->name = $n; return $this; }

    public function getRole(): string { return $this->role; }
    public function setRole(string $r): self
    {
        $this->role = $r;
        // Garde isZombie cohérent avec role si on passe par setRole
        if ($r === self::ROLE_ZOMBIE) {
            $this->isZombie = true;
        } elseif ($r === self::ROLE_HUMAN) {
            $this->isZombie = false;
        }
        return $this;
    }

    public function getLives(): int { return $this->lives; }
    public function setLives(int $v): self
    {
        $this->lives   = $v;
        $this->isAlive = $v > 0;
        return $this;
    }
    public function decLife(): self
    {
        $this->lives   = max(0, $this->lives - 1);
        $this->isAlive = $this->lives > 0;
        return $this;
    }
    public function incLife(): self
    {
        $this->lives++;
        return $this;
    }

    public function isAlive(): bool { return $this->isAlive; }
    public function setIsAlive(bool $b): self { $this->isAlive = $b; return $this; }

    public function isLockedInDuel(): bool { return $this->lockedInDuel; }
    public function setLockedInDuel(bool $b): self { $this->lockedInDuel = $b; return $this; }

    public function getIncomingDuel(): ?Game4Duel { return $this->incomingDuel; }
    public function setIncomingDuel(?Game4Duel $d): self { $this->incomingDuel = $d; return $this; }

    // ===== Helpers duel =====

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

    // ===== États Zombie/Elimination (synchronisés avec role) =====

    public function isZombie(): bool
    {
        return $this->isZombie;
    }

    public function setZombie(bool $isZombie): self
    {
        $this->isZombie = $isZombie;
        // Garde role cohérent avec isZombie si on passe par setZombie
        $this->role = $isZombie ? self::ROLE_ZOMBIE : self::ROLE_HUMAN;
        return $this;
    }

    public function isEliminated(): bool
    {
        return $this->isEliminated;
    }

    public function setEliminated(bool $isEliminated): self
    {
        $this->isEliminated = $isEliminated;
        if ($isEliminated) {
            $this->isAlive = false;
            $this->lockedInDuel = false;
            $this->incomingDuel = null;
        }
        return $this;
    }

    public function becomeZombie(): self
    {
        $this->role     = self::ROLE_ZOMBIE;
        $this->isZombie = true;
        return $this;
    }

    public function becomeHuman(): self
    {
        $this->role     = self::ROLE_HUMAN;
        $this->isZombie = false;
        return $this;
    }

    // Petit helper d’affichage si nécessaire
    public function getDisplayRole(): string
    {
        return $this->isZombie ? 'zombie' : 'human';
    }
}
