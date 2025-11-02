<?php

namespace App\Entity;

use App\Repository\Game4CardDefRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: Game4CardDefRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_g4carddef_code', columns: ['code'])]
class Game4CardDef
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 32)]
    private string $code;

    #[ORM\Column(length: 64)]
    private string $label;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $text = null;

    #[ORM\Column(length: 16)]
    private string $type;

    public function getId(): ?int { 
        return $this->id; 
    }

    public function getCode(): string { 
        return $this->code; 
    }
    
    public function setCode(string $code): self { 
        $this->code = $code; 
        return $this; 
    }

    public function getLabel(): string { 
        return $this->label; 
    }
    
    public function setLabel(string $label): self { 
        $this->label = $label; 
        return $this; 
    }

    public function getText(): ?string { 
        return $this->text; 
    }
    
    public function setText(?string $text): self { 
        $this->text = $text; 
        return $this; 
    }

    public function getType(): string { 
        return $this->type; 
    }
    
    public function setType(string $type): self { 
        $this->type = $type; 
        return $this; 
    }

    public function __toString(): string
    {
        return $this->code.' - '.$this->label;
    }
}