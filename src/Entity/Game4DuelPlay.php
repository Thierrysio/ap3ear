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
#[ORM\Index(name: 'idx_duel_type', columns: ['duel_id', 'card_type'])]
class Game4DuelPlay
{
    public const TYPE_NUM     = 'NUM';
    public const TYPE_ZOMBIE  = 'ZOMBIE';
    public const TYPE_SHOTGUN = 'SHOTGUN';
    public const TYPE_VACCINE = 'VACCINE';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    // Important : inversedBy="plays" car Game4Duel a OneToMany(mappedBy="duel")
    #[ORM\ManyToOne(targetEntity: Game4Duel::class, inversedBy: 'plays')]
    #[ORM\JoinColumn(name: 'duel_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Game4Duel $duel = null;

    #[ORM\ManyToOne(targetEntity: Game4Player::class)]
    #[ORM\JoinColumn(name: 'player_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Game4Player $player = null;

    // Lien conservé pour audit ; peut devenir NULL après défausse / déplacement
    #[ORM\ManyToOne(targetEntity: Game4Card::class)]
    #[ORM\JoinColumn(name: 'card_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Game4Card $card = null;

    // Code lisible côté client (ex: "NUM_5", "ZOMBIE", "SHOTGUN", "VACCINE")
    #[ORM\Column(type: 'string', length: 64)]
    private string $cardCode;

    // Type stable (NUM|ZOMBIE|SHOTGUN|VACCINE)
    #[ORM\Column(type: 'string', length: 16, name: 'card_type')]
    private string $cardType;

        $type = strtoupper($this->cardType);

        return \in_array($type, [self::TYPE_ZOMBIE, self::TYPE_SHOTGUN, self::TYPE_VACCINE], true);
        return strtoupper($this->cardType) === self::TYPE_NUM;
    private ?int $numValue = null;

    // Manche : 1..N (bornage métier côté service/contrôleur)
    #[ORM\Column(type: 'integer', name: 'round_index')]
    private int $roundIndex = 1;

    #[ORM\Column(type: 'datetime_immutable', name: 'submitted_at')]
    private \DateTimeImmutable $submittedAt;

    public function __construct()
    {
        $this->submittedAt = new \DateTimeImmutable();
    }

    // ===== Helpers métier =====
    public function isSpecial(): bool
    {
        return \in_array($this->cardType, [self::TYPE_ZOMBIE, self::TYPE_SHOTGUN, self::TYPE_VACCINE], true);
    }

    public function isNum(): bool
    {
        return $this->cardType === self::TYPE_NUM;
    }

    // ===== Getters / Setters =====
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDuel(): ?Game4Duel
    {
        return $this->duel;
    }

    public function setDuel(Game4Duel $duel): self
    {
        $this->duel = $duel;
        return $this;
    }

    public function getPlayer(): ?Game4Player
    {
        return $this->player;
    }

    public function setPlayer(Game4Player $player): self
    {
        $this->player = $player;
        return $this;
    }

    public function getCard(): ?Game4Card
    {
        return $this->card;
    }

    public function setCard(?Game4Card $card): self
    {
        $this->card = $card;
        return $this;
    }

    public function getCardCode(): string
    {
        return $this->cardCode;
    }

    public function setCardCode(string $cardCode): self
    {
        $this->cardCode = $cardCode;
        return $this;
    }

    public function getCardType(): string
    {
        return $this->cardType;
    }

    public function setCardType(string $cardType): self
    {
        $this->cardType = strtoupper(trim($cardType));

        return $this;
    }

    public function getNumValue(): ?int
    {
        return $this->numValue;
    }

    public function setNumValue(?int $numValue): self
    {
        $this->numValue = $numValue;
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

    public function getSubmittedAt(): \DateTimeImmutable
    {
        return $this->submittedAt;
    }

    public function setSubmittedAt(\DateTimeImmutable $submittedAt): self
    {
        $this->submittedAt = $submittedAt;
        return $this;
    }
}
