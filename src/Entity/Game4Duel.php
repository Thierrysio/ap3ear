<?php

namespace App\Entity;

use App\Repository\Game4DuelRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: Game4DuelRepository::class)]
#[ORM\Table(name: 'game4_duel')]
# -- Index utiles
#[ORM\Index(name: 'idx_g4duel_game_status', columns: ['game_id', 'status'])]
#[ORM\Index(name: 'idx_g4duel_game_playerA', columns: ['game_id', 'player_a_id'])]
#[ORM\Index(name: 'idx_g4duel_game_playerB', columns: ['game_id', 'player_b_id'])]
# -- Unicités (limitées par statut)
# Un seul duel PENDING pour une paire (A,B) donnée dans un jeu
#[ORM\UniqueConstraint(
    name: 'uniq_g4duel_pair_pending',
    columns: ['game_id', 'player_a_id', 'player_b_id', 'status']
)]
# Un seul duel PENDING impliquant A (quel que soit l’adversaire) dans un jeu
#[ORM\UniqueConstraint(
    name: 'uniq_duel_playerA_pending',
    columns: ['game_id', 'player_a_id', 'status']
)]
# Un seul duel PENDING impliquant B (quel que soit l’adversaire) dans un jeu
#[ORM\UniqueConstraint(
    name: 'uniq_duel_playerB_pending',
    columns: ['game_id', 'player_b_id', 'status']
)]
class Game4Duel
{
    public const STATUS_PENDING  = 'PENDING';
    public const STATUS_RESOLVED = 'RESOLVED';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Game4Game::class)]
    #[ORM\JoinColumn(name: 'game_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Game4Game $game = null;

    #[ORM\ManyToOne(targetEntity: Game4Player::class)]
    #[ORM\JoinColumn(name: 'player_a_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Game4Player $playerA = null;

    #[ORM\ManyToOne(targetEntity: Game4Player::class)]
    #[ORM\JoinColumn(name: 'player_b_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Game4Player $playerB = null;

    #[ORM\Column(type: 'string', length: 16)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $roundIndex = 0;

    // Relation plutôt qu’un int brut (gagnant nullable en cas d’égalité)
    #[ORM\ManyToOne(targetEntity: Game4Player::class)]
    #[ORM\JoinColumn(name: 'winner_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Game4Player $winner = null;

    #[ORM\Column(type: 'datetime_immutable', name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', name: 'resolved_at', nullable: true)]
    private ?\DateTimeImmutable $resolvedAt = null;

    // Stockage simple : lignes séparées par \n
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $logs = null;

    // Inverse side des coups du duel
    #[ORM\OneToMany(mappedBy: 'duel', targetEntity: Game4DuelPlay::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $plays;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->plays = new ArrayCollection();
    }

    // ==== Getters/Setters ====

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getGame(): ?Game4Game
    {
        return $this->game;
    }

    public function setGame(Game4Game $game): self
    {
        $this->game = $game;
        return $this;
    }

    public function getPlayerA(): ?Game4Player
    {
        return $this->playerA;
    }

    public function setPlayerA(Game4Player $playerA): self
    {
        $this->playerA = $playerA;
        return $this;
    }

    public function getPlayerB(): ?Game4Player
    {
        return $this->playerB;
    }

    public function setPlayerB(Game4Player $playerB): self
    {
        $this->playerB = $playerB;
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

    public function getRoundIndex(): int
    {
        return $this->roundIndex;
    }

    public function setRoundIndex(int $roundIndex): self
    {
        $this->roundIndex = $roundIndex;
        return $this;
    }

    public function getWinner(): ?Game4Player
    {
        return $this->winner;
    }

    public function setWinner(?Game4Player $winner): self
    {
        $this->winner = $winner;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getResolvedAt(): ?\DateTimeImmutable
    {
        return $this->resolvedAt;
    }

    public function setResolvedAt(?\DateTimeImmutable $resolvedAt): self
    {
        $this->resolvedAt = $resolvedAt;
        return $this;
    }

    public function getLogs(): ?string
    {
        return $this->logs;
    }

    public function setLogs(?string $logs): self
    {
        $this->logs = $logs;
        return $this;
    }

    public function setLogsArray(array $lines): self
    {
        $this->logs = implode("\n", $lines);
        return $this;
    }

    public function getLogsArray(): array
    {
        return $this->logs ? preg_split("/\r?\n/", $this->logs) : [];
    }

    /** @return Collection|Game4DuelPlay[] */
    public function getPlays(): Collection
    {
        return $this->plays;
    }

    public function addPlay(Game4DuelPlay $play): self
    {
        if (!$this->plays->contains($play)) {
            $this->plays->add($play);
            $play->setDuel($this);
        }
        return $this;
    }

    public function removePlay(Game4DuelPlay $play): self
    {
        // Avec orphanRemoval=true et cascade remove,
        // retirer de la collection suffit à provoquer la suppression.
        $this->plays->removeElement($play);
        return $this;
    }

    // ==== Helpers ====

    /** Vrai si le joueur fait partie du duel */
    public function involves(Game4Player $player): bool
    {
        return $this->getPlayerA()?->getId() === $player->getId()
            || $this->getPlayerB()?->getId() === $player->getId();
    }

    public function getOpponentFor(Game4Player $player): ?Game4Player
    {
        if ($this->getPlayerA()?->getId() === $player->getId()) {
            return $this->getPlayerB();
        }
        if ($this->getPlayerB()?->getId() === $player->getId()) {
            return $this->getPlayerA();
        }
        return null;
    }

    /**
     * Normalise l’ordre (A,B) pour limiter les doublons :
     * A = joueur avec l’ID le plus petit.
     */
    public function setPairNormalized(Game4Player $p1, Game4Player $p2): self
    {
        if ($p1->getId() <= $p2->getId()) {
            $this->playerA = $p1;
            $this->playerB = $p2;
        } else {
            $this->playerA = $p2;
            $this->playerB = $p1;
        }
        return $this;
    }

    /** Marque le duel comme résolu, fixe gagnant + date + logs */
    public function markResolved(?Game4Player $winner, array $logs = []): self
    {
        $this->status = self::STATUS_RESOLVED;
        $this->winner = $winner;
        $this->resolvedAt = new \DateTimeImmutable();
        if ($logs) {
            $this->setLogsArray($logs);
        }
        return $this;
    }
}
