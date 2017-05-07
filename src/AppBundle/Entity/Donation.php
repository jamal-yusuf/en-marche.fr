<?php

namespace AppBundle\Entity;

use Algolia\AlgoliaSearchBundle\Mapping\Annotation as Algolia;
use AppBundle\Exception\InitializedEntityException;
use AppBundle\Geocoder\GeoPointInterface;
use Doctrine\ORM\Mapping as ORM;
use libphonenumber\PhoneNumber;
use Ramsey\Uuid\Uuid;

/**
 * @ORM\Table(name="donations")
 * @ORM\Entity(repositoryClass="AppBundle\Repository\DonationRepository")
 *
 * @Algolia\Index(autoIndex=false)
 */
class Donation implements GeoPointInterface
{
    use EntityIdentityTrait;
    use EntityCrudTrait;
    use EntityPostAddressTrait;
    use EntityPersonNameTrait;

    /**
     * @ORM\Column(type="integer")
     */
    private $amount;

    /**
     * @ORM\Column(length=6)
     */
    private $gender;

    /**
     * @ORM\Column
     */
    private $emailAddress;

    /**
     * @ORM\Column(type="phone_number", nullable=true)
     */
    private $phone;

    /**
     * @ORM\Column(length=100, nullable=true)
     */
    private $payboxResultCode;

    /**
     * @ORM\Column(length=100, nullable=true)
     */
    private $payboxAuthorizationCode;

    /**
     * @ORM\Column(type="json_array", nullable=true)
     */
    private $payboxPayload;

    /**
     * @ORM\Column(type="boolean")
     */
    private $finished = false;

    /**
     * @ORM\Column(length=50, nullable=true)
     */
    private $clientIp;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $donatedAt;

    /**
     * @ORM\Column(type="datetime")
     */
    private $createdAt;

    public function __construct(
        int $amount,
        string $gender,
        string $firstName,
        string $lastName,
        string $emailAddress,
        PostAddress $postAddress,
        ?PhoneNumber $phone
    ) {
        $this->amount = $amount;
        $this->gender = $gender;
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->emailAddress = $emailAddress;
        $this->postAddress = $postAddress;
        $this->phone = $phone;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function __toString(): string
    {
        return trim($this->lastName.' '.$this->firstName.' ('.($this->amount / 100).' €)');
    }

    public function init(string $clientIp): void
    {
        if (null !== $this->uuid) {
            throw new InitializedEntityException($this);
        }

        $this->uuid = Uuid::uuid4();
        $this->clientIp = $clientIp;
    }

    public function finish(array $payboxPayload): void
    {
        $this->finished = true;
        $this->payboxPayload = $payboxPayload;
        $this->payboxResultCode = $payboxPayload['result'];

        if (isset($payboxPayload['authorization'])) {
            $this->payboxAuthorizationCode = $payboxPayload['authorization'];
        }

        if ($this->payboxResultCode === '00000') {
            $this->donatedAt = new \DateTime();
        }
    }

    public function isFinished(): bool
    {
        return $this->finished;
    }

    public function isSuccessful(): bool
    {
        return $this->finished && $this->donatedAt;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function getAmountInEuros(): float
    {
        return (float) $this->amount / 100;
    }

    public function getGender(): string
    {
        return $this->gender;
    }

    public function getEmailAddress(): string
    {
        return $this->emailAddress;
    }

    public function getPhone(): PhoneNumber
    {
        return $this->phone;
    }

    public function getPayboxResultCode(): ?string
    {
        return $this->payboxResultCode;
    }

    public function getPayboxAuthorizationCode(): ?string
    {
        return $this->payboxAuthorizationCode;
    }

    public function getPayboxPayload(): ?array
    {
        return $this->payboxPayload;
    }

    public function getPayboxPayloadAsJson(): string
    {
        return json_encode($this->payboxPayload, JSON_PRETTY_PRINT);
    }

    public function getFinished(): bool
    {
        return $this->finished;
    }

    public function getClientIp(): ?string
    {
        return $this->clientIp;
    }

    public function getDonatedAt(): ?\DateTimeInterface
    {
        return $this->donatedAt;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getRetryPayload(): array
    {
        $payload = [
            'ge' => $this->gender,
            'ln' => $this->lastName,
            'fn' => $this->firstName,
            'em' => urlencode($this->emailAddress),
            'co' => $this->getCountry(),
            'pc' => $this->getPostalCode(),
            'ci' => $this->getCityName(),
            'ad' => urlencode($this->getAddress()),
        ];

        if ($this->phone) {
            $payload['phc'] = $this->phone->getCountryCode();
            $payload['phn'] = $this->phone->getNationalNumber();
        }

        return $payload;
    }
}
