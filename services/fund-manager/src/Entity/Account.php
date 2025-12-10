<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Repository\AccountRepository;
use App\State\AccountProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: AccountRepository::class)]
#[ORM\Table(name: 'account')]
#[ApiResource(
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => ['account:read']],
            security: "is_granted('ROLE_USER')"
        ),
        new Post(
            denormalizationContext: ['groups' => ['account:write']],
            normalizationContext: ['groups' => ['account:read']],
            processor: AccountProcessor::class,
            security: "is_granted('ROLE_USER')"
        ),
        new Get(
            normalizationContext: ['groups' => ['account:read']],
            security: 'object.getUser().getEmail() == user.getUserIdentifier()'
        ),
        new Patch(
            denormalizationContext: ['groups' => ['account:write']],
            normalizationContext: ['groups' => ['account:read']],
            security: 'object.getUser().getEmail() == user.getUserIdentifier()'
        ),
        new Delete(
            security: 'object.getUser().getEmail() == user.getUserIdentifier()'
        ),
    ]
)]
class Account
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['account:read'])]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 36, unique: true)]
    #[Groups(['account:read'])]
    private string $accountUuid;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_uuid', referencedColumnName: 'uuid', nullable: false)]
    #[Groups(['account:read'])] // owner info readable, but not writable directly
    private User $user;

    #[ORM\Column(type: 'decimal', precision: 18, scale: 2)]
    #[Groups(['account:read', 'account:write'])]
    private string $balance = '0.00';

    #[ORM\Column(type: 'string', length: 3)]
    #[Groups(['account:read', 'account:write'])]
    private string $currency = 'INR';

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['account:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['account:read'])]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->accountUuid = Uuid::v4()->toRfc4122();
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAccountUuid(): string
    {
        return $this->accountUuid;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getBalance(): string
    {
        return $this->balance;
    }

    public function setBalance(string $balance): self
    {
        $this->balance = $balance;

        return $this;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): self
    {
        $this->currency = $currency;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function touch(): self
    {
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }
}
