<?php

namespace AppBundle\Membership;

use AppBundle\Donation\DonationRequestHelper;
use AppBundle\Entity\Adherent;
use AppBundle\Entity\Donation;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

final class MembershipUtils
{
    const REGISTERING_DONATION = 'membership.registering_donation';
    const NEW_ADHERENT_ID = 'membership.new_adherent_id';

    private $donationRequestHelper;
    private $session;

    public function __construct(DonationRequestHelper $donationRequestHelper, SessionInterface $session)
    {
        $this->donationRequestHelper = $donationRequestHelper;
        $this->session = $session;
    }

    public function createRegisteringDonation(Adherent $adherent)
    {
        $donationRequest = $this->donationRequestHelper->createFromAdherent($adherent);

        $this->session->set(self::REGISTERING_DONATION, $donationRequest);
        $this->session->set(self::NEW_ADHERENT_ID, $adherent->getId());
    }

    /**
     * @return Donation|null
     */
    public function getRegisteringDonation()
    {
        return $this->session->get(self::REGISTERING_DONATION);
    }

    public function clearRegisteringDonation()
    {
        $this->session->remove(self::REGISTERING_DONATION);
    }

    /**
     * Returns the id of the new adherent during registration's steps.
     */
    public function getNewAdherentId(): ?int
    {
        return $this->session->get(self::NEW_ADHERENT_ID);
    }

    /**
     * If true, the current user has started the subscription process but hasn't finished yet.
     */
    public function isInSubscriptionProcess(): bool
    {
        return (bool) $this->getNewAdherentId();
    }

    public function clearNewAdherentId()
    {
        $this->session->remove(self::NEW_ADHERENT_ID);
    }
}
