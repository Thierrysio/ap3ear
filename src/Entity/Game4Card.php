<?php

namespace App\Entity;

use App\Repository\Game4CardRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: Game4CardRepository::class)]
#[ORM\Index(name: 'idx_game_zone', columns: ['game_id', 'zone'])]
#[ORM\UniqueConstraint(name: 'uniq_token', columns: ['token'])]
class Game4Card
{
    public const ZONE_DECK = 'DECK';
    public const ZONE_HAND = 'HAND';
    public const ZONE_DISCARD = 'DISCARD';
    public const ZONE_BURN = 'BURN';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Game4Game $game;

    #[ORM\ManyToOne(targetEntity: Game4CardDef::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Game4CardDef $def;

    #[ORM\ManyToOne]
    private ?Game4Player $owner = null;

    #[ORM\Column(length: 16)]
    private string $zone = self::ZONE_DECK;

    #[ORM\Column(length: 128, unique: true)]
    private string $token;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getGame(): Game4Game
    {
        return $this->game;
    }

    public function setGame(Game4Game $g): self
    {
        $this->game = $g;

        return $this;
    }

    public function getDef(): Game4CardDef
    {
        return $this->def;
    }

    public function setDef(Game4CardDef $d): self
    {
        $this->def = $d;

        return $this;
    }

    public function getOwner(): ?Game4Player
    {
        return $this->owner;
    }

    public function setOwner(?Game4Player $p): self
    {
        $this->owner = $p;

        return $this;
    }

    public function getZone(): string
    {
        return $this->zone;
    }

    public function setZone(string $z): self
    {
        $this->zone = $z;

        return $this;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function setToken(string $t): self
    {
        $this->token = $t;

        return $this;
    }
}
