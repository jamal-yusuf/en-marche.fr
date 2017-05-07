<?php

namespace AppBundle\Donation;

use AppBundle\Entity\Adherent;
use AppBundle\Entity\Donation;
use AppBundle\Exception\InvalidDonationTokenException;
use Doctrine\ORM\EntityManager;
use libphonenumber\PhoneNumber;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class DonationRequestHelper
{
    private const CALLBACK_TOKEN = 'donation_callback_token';
    private const RETRY_TOKEN = 'donation_retry_token';
    private const RETRY_PAYLOAD = 'donation_retry_payload';
    private const PAYBOX_SUCCESS = 'donation_paybox_success';
    private const PAYBOX_UNKNOWN = 'donation_paybox_unknown';
    private const PAYBOX_STATUSES = [
        // Success
        '00000' => self::PAYBOX_SUCCESS,

        // Platform or authorization center error
        '00001' => 'paybox',
        '00003' => 'paybox',

        // Invalid card number/validity
        '00004' => 'invalid-card',
        '00008' => 'invalid-card',
        '00021' => 'invalid-card',

        // Timeout
        '00030' => 'timeout',

        // Other
        self::PAYBOX_UNKNOWN => 'error',
    ];

    private $validator;
    private $tokenManager;
    private $entityManager;

    public function __construct(ValidatorInterface $validator, CsrfTokenManagerInterface $tokenManager, EntityManager $entityManager)
    {
        $this->validator = $validator;
        $this->tokenManager = $tokenManager;
        $this->entityManager = $entityManager;
    }

    public function createFromRequest(Request $request, float $amount, $currentUser = null): DonationRequest
    {
        if ($currentUser instanceof Adherent) {
            $donation = DonationRequest::createFromAdherent($currentUser, $amount);
        } else {
            $donation = new DonationRequest($amount);
        }

        if (!$request->query->has(self::RETRY_PAYLOAD)) {
            return $donation;
        }

        $retry = clone $donation;
        $payload = json_decode($request->get(self::RETRY_PAYLOAD),true);

        if (isset($payload['ge']) && in_array($payload['ge'], ['male', 'female'], true)) {
            $retry->setGender($payload['ge']);
        }

        if (isset($payload['ln'])) {
            $retry->setLastName((string) $payload['ln']);
        }

        if (isset($payload['fn'])) {
            $retry->setFirstName((string) $payload['fn']);
        }

        if (isset($payload['em'])) {
            $retry->setEmailAddress(urldecode($payload['em']));
        }

        if ($payload['co']) {
            $retry->setCountry((string) $payload['co']);
        }

        if (isset($payload['pc'])) {
            $retry->setPostalCode((string) $payload['pc']);
        }

        if (isset($payload['ci'])) {
            $retry->setCityName((string) $payload['ci']);
        }

        if (isset($payload['cn'])) {
            $retry->setCityName((string) $payload['cn']);
        }

        if (isset($payload['ad'])) {
            $retry->setAddress(urldecode($payload['ad']));
        }

        if (isset($payload['phc']) && isset($payload['phn'])) {
            $phone = new PhoneNumber();
            $phone->setCountryCode((string) $payload['phc']);
            $phone->setNationalNumber((string) $payload['phn']);

            $retry->setPhone($phone);
        }

        if ($this->validateRetryPayload($payload, $retry)) {
            return $retry;
        }

        return $donation;
    }

    public function createFromAdherent(Adherent $adherent, int $defaultAmount = 50): DonationRequest
    {
        $donation = DonationRequest::createFromAdherent($adherent, $defaultAmount);

        return $donation;
    }


    public function createPayload(Donation $donation, Request $request): array
    {
        $this->validateCallbackStatus($request);

        $payload = $donation->getRetryPayload();
        $payload['_token'] = $this->tokenManager->getToken(self::RETRY_TOKEN);

        return [
            self::RETRY_PAYLOAD => json_encode($payload),
            'montant' => $donation->getAmountInEuros(),
        ];
    }

    public function createCallbackStatus(Donation $donation): array
    {
        $code = self::PAYBOX_STATUSES[$donation->getPayboxResultCode()] ?? self::PAYBOX_UNKNOWN;

        return [
            'code' => $code,
            'uuid' => $donation->getUuid()->toString(),
            'status' => self::PAYBOX_SUCCESS === $code ? 'effectue' : 'erreur',
            '_token' => $this->tokenManager->getToken(self::CALLBACK_TOKEN),
        ];
    }

    private function validateCallbackStatus(Request $request): void
    {
        if ($this->tokenManager->isTokenValid(new CsrfToken(self::CALLBACK_TOKEN, $request->query->get(self::CALLBACK_TOKEN)))
            && isset(self::PAYBOX_STATUSES[$request->query->getAlnum('code')])
        ) {
            return;
        }

        throw new InvalidDonationTokenException();
    }

    private function validateRetryPayload(array $payload, DonationRequest $donationRequest): bool
    {
        if (!isset($payload[self::RETRY_TOKEN])
            || !$this->tokenManager->isTokenValid(new CsrfToken(self::RETRY_TOKEN, $payload[self::RETRY_TOKEN]))
        ) {
            throw new InvalidDonationTokenException();
        }

        return 0 === count($this->validator->validate($donationRequest));
    }
}
