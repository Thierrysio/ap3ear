<?php

namespace App\Entity;

use App\Repository\Game4DuelPlayRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: Game4DuelPlayRepository::class)]
#[ORM\Table(name: 'game4_duel_play')]
#[ORM\UniqueConstraint(name: 'uniq_duel_player_round', columns: ['duel_id', 'player_id', 'round_index'])]
#[ORM\Index(name: 'idx_duel', columns: ['duel_id'])]
#[ORM\Index(name: 'idx_duel_player', columns: ['duel_id', 'player_id'])]
#[ORM\Index(name: 'idx_duel_round', columns: ['duel_id', 'round_index'])]
class Game4DuelPlay
{
    public const TYPE_NUM     = 'NUM';
    public const TYPE_ZOMBIE  = 'ZOMBIE';
    public const TYPE_SHOTGUN = 'SHOTGUN';
    public const TYPE_VACCINE = 'VACCINE';

    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: 'integer')]
    private ?int $id = null;

    // Important : inversedBy="plays" car Game4Duel a OneToMany(mappedBy="duel")
    #[ORM\ManyToOne(targetEntity: Game4Duel::class, inversedBy: 'plays')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Game4Duel $duel = null;

    #[ORM\ManyToOne(targetEntity: Game4Player::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Game4Player $player = null;

    // Lien conservé pour audit ; peut devenir NULL après défausse
    #[ORM\ManyToOne(targetEntity: Game4Card::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Game4Card $card = null;

    // Code lisible côté client (ex: "NUM_5", "ZOMBIE", "SHOTGUN", "VACCINE")
    #[ORM\Column(type: 'string', length: 64)]
    private string $cardCode;

    // Type stable (NUM|ZOMBIE|SHOTGUN|VACCINE)
    #[ORM\Column(type: 'string', length: 16)]
    private string $cardType;

    // Manche : 1..4 (bornage métier côté contrôleur/service)
    #[ORM\Column(type: 'integer', name: 'round_index')]
    private int $roundIndex = 1;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $submittedAt;

    public function __construct()
    {
        $this->submittedAt = new \DateTimeImmutable();
    }

    // ===== Getters / Setters =====
    public function getId(): ?int { return $this->id; }

    public function getDuel(): ?Game4Duel { return $this->duel; }
    public function setDuel(Game4Duel $d): self { $this->duel = $d; return $this; }

    public function getPlayer(): ?Game4Player { return $this->player; }
    public function setPlayer(Game4Player $p): self { $this->player = $p; return $this; }

    public function getCard(): ?Game4Card { return $this->card; }
    public function setCard(?Game4Card $c): self { $this->card = $c; return $this; }

    public function getCardCode(): string { return $this->cardCode; }
    public function setCardCode(string $c): self { $this->cardCode = $c; return $this; }

    public function getCardType(): string { return $this->cardType; }
    public function setCardType(string $t): self { $this->cardType = $t; return $this; }

    public function getRoundIndex(): int { return $this->roundIndex; }
    public function setRoundIndex(int $r): self { $this->roundIndex = $r; return $this; }

    public function getSubmittedAt(): \DateTimeImmutable { return $this->submittedAt; }
    public function setSubmittedAt(\DateTimeImmutable $d): self { $this->submittedAt = $d; return $this; }
}
