<?php

namespace Application\Bundle\DefaultBundle\Tests;

use Liip\FunctionalTestBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Client;
use Doctrine\ORM\EntityManager;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Class TicketControllerTest.
 */
class TicketControllerTest extends WebTestCase
{
    /** @var Client */
    protected $client;
    /** @var EntityManager */
    protected $em;

    protected $translator;

    /** set up fixtures */
    public function setUp()
    {
        $this->loadFixtures(
            [
                'Stfalcon\Bundle\EventBundle\DataFixtures\ORM\LoadEventData',
                'Application\Bundle\UserBundle\DataFixtures\ORM\LoadUserData',
                'Stfalcon\Bundle\EventBundle\DataFixtures\ORM\LoadPaymentData',
                'Stfalcon\Bundle\EventBundle\DataFixtures\ORM\LoadTicketData',
            ]
        );
        $this->client = $this->createClient();
        $this->em = $this->getContainer()->get('doctrine')->getManager();
        $this->translator = $this->getContainer()->get('translator');
    }

    /** destroy */
    public function tearDown()
    {
        parent::tearDown();
    }

    /**
     * test login user
     */
    public function testEnTicketHash()
    {
        $this->loginUser('user@fwdays.com', 'qwerty');
        $crawler = $this->client->request('GET', '/en/event/javaScript-framework-day-2018/ticket');
        $crawler->html();
    }

    /**
     * @param string $userName
     * @param string $userPass
     */
    private function loginUser($userName, $userPass)
    {
        $user = $this->em->getRepository('ApplicationUserBundle:User')->findOneBy(['email' => $userName]);

        /** start Login */
        $crawler = $this->client->request('GET', '/login');
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $this->assertContains('<button class="btn btn--primary btn--lg form-col__btn" type="submit">Login</button>', $crawler->html());
        $form = $crawler->selectButton('Login')->form();
        $form['_username'] = $user->getEmail();
        $form['_password'] = $userPass;
        $this->client->followRedirects();
        $crawler = $this->client->submit($form);
        /** end Login */
        $crawler = $this->client->request('GET', '/');
        $this->assertGreaterThan(0, $crawler->filter('a:contains(" Сabinet")')->count());
    }
}
