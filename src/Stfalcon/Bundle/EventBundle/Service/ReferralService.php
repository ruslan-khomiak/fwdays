<?php

namespace Stfalcon\Bundle\EventBundle\Service;

use Application\Bundle\UserBundle\Services\UserBalanceService;
use Stfalcon\Bundle\EventBundle\Entity\Payment;
use Symfony\Component\DependencyInjection\Container;
use Application\Bundle\UserBundle\Entity\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Сервис для работы с реферальной программой
 */
class ReferralService
{
    const REFERRAL_CODE  = 'REFERRALCODE';
    const REFERRAL_BONUS = 100;

    /**
     * @var Container $container
     */
    protected $container;

    /**
     * @var Request $request
     */
    protected $request;

    protected $userBalanceService;
    /**
     * @param Container          $container
     * @param UserBalanceService $userBalanceService
     */
    public function __construct($container, $userBalanceService)
    {
        $this->container = $container;
        $this->request   = $this->container->get('request');
        $this->userBalanceService = $userBalanceService;
    }

    /**
     * Ger referral code
     *
     * @param User|null $user User
     *
     * @return mixed
     */
    public function getReferralCode($user = null)
    {
        if (is_null($user)) {
            $user = $this->container->get('security.token_storage')->getToken()->getUser();
        }

        $referralCode = $user->getReferralCode();

        if (true === empty($referralCode)) {

            $user->setReferralCode(md5($user->getEmail().time()));
            $em = $this->container->get('doctrine.orm.default_entity_manager');

            $em->persist($user);
            $em->flush();
        }

        return $user->getReferralCode();
    }

    /**
     * Начисляет рефералы
     *
     * @param Payment $payment
     */
    public function chargingReferral(Payment $payment)
    {
        $this->userBalanceService->setUserBalance($payment, UserBalanceService::INCOME);
    }

    /**
     * Списуєм реферальні средства
     *
     * @param Payment $payment
     */
    public function utilizeBalance(Payment $payment)
    {
       $this->userBalanceService->setUserBalance($payment, UserBalanceService::OUTCOME);
    }

    /**
     * @param $referralCode
     * @return User|null
     * @throws \Exception
     */
    public function getUserByReferralCode($referralCode)
    {
        $em = $this->container->get('doctrine.orm.default_entity_manager');

        $referralUser = $em->getRepository('ApplicationUserBundle:User')
            ->findOneBy(['referralCode' => $referralCode]);

        return $referralUser;
    }

    /**
     * Save ref code in cookies
     *
     * @param Request $request
     *
     * @return bool
     */
    public function handleRequest($request)
    {
        if ($request->query->has('ref')) {
            $code = $request->query->get('ref');

            //уже используется реф. код
            if (false == $request->cookies->has(self::REFERRAL_CODE)) {

                $user = $this->getUser();

                //user authorize
                if (!is_null($user)) {
                    if ($user->getReferralCode() == $code) {
                        return false;
                    }

                    $userReferral = $this->getUserByReferralCode($code);

                    if ($userReferral) {
                        $em = $this->container->get('doctrine.orm.default_entity_manager');

                        $user->setUserReferral($userReferral);

                        $em->persist($user);
                        $em->flush();
                    }
                }

                $response = new Response();
                $expire = time() + (10 * 365 * 24 * 3600);

                $response->headers->setCookie(new Cookie(self::REFERRAL_CODE, $code, $expire));
                $response->send();
            }
        }
    }

    /**
     * Get user
     *
     * @return User|null
     *
     * @throws \Exception
     */
    private function getUser()
    {
        if (null === $token = $this->container->get('security.token_storage')->getToken()) {
            return null;
        }

        if (!is_object($user = $token->getUser())) {
            return null;
        }

        return $user;
    }
}
