<?php

namespace App\Entity;

use App\Repository\QrScanRepository;
use App\Utils\UuidGenerator;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: QrScanRepository::class)]
class QrScan
{
    public const STATUS_ACCEPTED = 'ACCEPTED';
    public const STATUS_REJECTED = 'REJECTED';
    public const STATUS_LATE = 'LATE';
    public const STATUS_DUPLICATE = 'DUPLICATE';
    public const STATUS_OUT_OF_RADIUS = 'OUT_OF_RADIUS';
    public const STATUS_INVALID_QR = 'INVALID_QR';

    #[ORM\Id]
    #[ORM\Column(type: 'guid', unique: true)]
    private string $id;

    #[ORM\ManyToOne(inversedBy: 'qrScans')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Round $round = null;

    #[ORM\ManyToOne(inversedBy: 'qrScans')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Team $team = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $scannedAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $clientTimestamp = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $gpsLat = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $gpsLng = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $gpsAccuracy = null;

    #[ORM\Column(length: 20)]
    private string $scanStatus;

    #[ORM\Column(nullable: true)]
    private ?int $rank = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $rejectionReason = null;

    public function __construct()
    {
        $this->id = UuidGenerator::generate();
        $this->scannedAt = new DateTimeImmutable();
        $this->scanStatus = self::STATUS_REJECTED;
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

    public function getTeam(): ?Team
    {
        return $this->team;
    }

    public function setTeam(?Team $team): self
    {
        $this->team = $team;

        return $this;
    }

    public function getScannedAt(): DateTimeImmutable
    {
        return $this->scannedAt;
    }

    public function setScannedAt(DateTimeImmutable $scannedAt): self
    {
        $this->scannedAt = $scannedAt;

        return $this;
    }

    public function getClientTimestamp(): ?DateTimeImmutable
    {
        return $this->clientTimestamp;
    }

    public function setClientTimestamp(?DateTimeImmutable $clientTimestamp): self
    {
        $this->clientTimestamp = $clientTimestamp;

        return $this;
    }

    public function getGpsLat(): ?float
    {
        return $this->gpsLat;
    }

    public function setGpsLat(?float $gpsLat): self
    {
        $this->gpsLat = $gpsLat;

        return $this;
    }

    public function getGpsLng(): ?float
    {
        return $this->gpsLng;
    }

    public function setGpsLng(?float $gpsLng): self
    {
        $this->gpsLng = $gpsLng;

        return $this;
    }

    public function getGpsAccuracy(): ?float
    {
        return $this->gpsAccuracy;
    }

    public function setGpsAccuracy(?float $gpsAccuracy): self
    {
        $this->gpsAccuracy = $gpsAccuracy;

        return $this;
    }

    public function getScanStatus(): string
    {
        return $this->scanStatus;
    }

    public function setScanStatus(string $scanStatus): self
    {
        $this->scanStatus = $scanStatus;

        return $this;
    }

    public function getRank(): ?int
    {
        return $this->rank;
    }

    public function setRank(?int $rank): self
    {
        $this->rank = $rank;

        return $this;
    }

    public function getRejectionReason(): ?string
    {
        return $this->rejectionReason;
    }

    public function setRejectionReason(?string $rejectionReason): self
    {
        $this->rejectionReason = $rejectionReason;

        return $this;
    }
}
