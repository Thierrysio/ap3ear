<?php

namespace App\Entity;

use App\Repository\Game4PlayerRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: Game4PlayerRepository::class)]
#[ORM\Table(name: 'game4_player')]
#[ORM\Index(name: 'idx_g4player_game_alive', columns: ['game_id', 'is_alive'])]
#[ORM\Index(name: 'idx_g4player_game_role', columns: ['game_id', 'role'])]
#[ORM\Index(name: 'idx_g4player_game_locked', columns: ['game_id', 'locked_in_duel'])]
class Game4Player
{
    public const ROLE_HUMAN      = 'human';
    public const ROLE_ZOMBIE     = 'zombie';
    public const ROLE_ELIMINATED = 'eliminated';

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
    #[Assert\Choice(choices: [self::ROLE_HUMAN, self::ROLE_ZOMBIE, self::ROLE_ELIMINATED])]
    private string $role = self::ROLE_HUMAN;

    #[ORM\Column(type: 'integer', options: ['default' => 3], name: 'lives')]
    private int $lives = 3;

    #[ORM\Column(type: 'boolean', options: ['default' => true], name: 'is_alive')]
    private bool $isAlive = true;

    #[ORM\Column(type: 'boolean', options: ['default' => false], name: 'locked_in_duel')]
    private bool $lockedInDuel = false;

    #[ORM\Column(type: 'boolean', options: ['default' => false], name: 'is_zombie')]
    private bool $isZombie = false;

    #[ORM\Column(type: 'boolean', options: ['default' => false], name: 'is_eliminated')]
    private bool $isEliminated = false;

    #[ORM\ManyToOne(targetEntity: Game4Duel::class)]
    #[ORM\JoinColumn(name: 'incoming_duel_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Game4Duel $incomingDuel = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getGame(): Game4Game
    {
        return $this->game;
    }

    public function setGame(Game4Game $game): self
    {
        $this->game = $game;

        return $this;
    }

    public function getEquipeId(): int
    {
        return $this->equipeId;
    }

    public function setEquipeId(int $equipeId): self
    {
        $this->equipeId = $equipeId;

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

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $role): self
    {
        $role = strtolower($role);

        if (!\in_array($role, [self::ROLE_HUMAN, self::ROLE_ZOMBIE, self::ROLE_ELIMINATED], true)) {
            throw new \InvalidArgumentException(sprintf('Invalid role "%s".', $role));
        }

        $this->role        = $role;
        $this->isZombie    = $role === self::ROLE_ZOMBIE;
        $this->isEliminated = $role === self::ROLE_ELIMINATED;

        if ($this->isEliminated) {
            $this->isAlive       = false;
            $this->lockedInDuel  = false;
            $this->incomingDuel  = null;
            $this->isZombie      = false;
        }

        return $this;
    }

    public function getLives(): int
    {
        return $this->lives;
    }

    public function setLives(int $lives): self
    {
        $this->lives   = $lives;
        $this->isAlive = $lives > 0 && !$this->isEliminated;

        return $this;
    }

    public function decLife(): self
    {
        $this->lives   = max(0, $this->lives - 1);
        $this->isAlive = $this->lives > 0 && !$this->isEliminated;

        return $this;
    }

    public function incLife(): self
    {
        $this->lives++;

        if ($this->lives > 0 && !$this->isEliminated) {
            $this->isAlive = true;
        }

        return $this;
    }

    public function isAlive(): bool
    {
        return $this->isAlive;
    }

    public function setIsAlive(bool $isAlive): self
    {
        $this->isAlive = $isAlive && !$this->isEliminated;

        return $this;
    }

    public function isLockedInDuel(): bool
    {
        return $this->lockedInDuel;
    }

    public function setLockedInDuel(bool $lockedInDuel): self
    {
        $this->lockedInDuel = $lockedInDuel;

        return $this;
    }

    public function getIncomingDuel(): ?Game4Duel
    {
        return $this->incomingDuel;
    }

    public function setIncomingDuel(?Game4Duel $incomingDuel): self
    {
        $this->incomingDuel = $incomingDuel;

        return $this;
    }

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

    public function isZombie(): bool
    {
        return $this->isZombie;
    }

    public function setZombie(bool $isZombie): self
    {
        $this->isZombie = $isZombie;

        if ($this->isEliminated) {
            $this->role     = self::ROLE_ELIMINATED;
            $this->isZombie = false;
        } else {
            $this->role = $isZombie ? self::ROLE_ZOMBIE : self::ROLE_HUMAN;
        }

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
            $this->isAlive      = false;
            $this->lockedInDuel = false;
            $this->incomingDuel = null;
            $this->isZombie     = false;
            $this->role         = self::ROLE_ELIMINATED;
        } else {
            $this->role = $this->isZombie ? self::ROLE_ZOMBIE : self::ROLE_HUMAN;
        }

        return $this;
    }

    public function becomeZombie(): self
    {
        $this->isEliminated = false;
        $this->isAlive      = true;
        $this->isZombie     = true;
        $this->role         = self::ROLE_ZOMBIE;

        return $this;
    }

    public function becomeHuman(): self
    {
        $this->isEliminated = false;
        $this->isAlive      = true;
        $this->isZombie     = false;
        $this->role         = self::ROLE_HUMAN;

        return $this;
    }

    public function getDisplayRole(): string
    {
        if ($this->isEliminated) {
            return self::ROLE_ELIMINATED;
        }

        return $this->isZombie ? self::ROLE_ZOMBIE : self::ROLE_HUMAN;
    }
}
