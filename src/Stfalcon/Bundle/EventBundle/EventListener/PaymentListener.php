<?php

namespace Stfalcon\Bundle\EventBundle\EventListener;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Stfalcon\Bundle\EventBundle\Entity\Payment,
    Stfalcon\Bundle\EventBundle\Entity\Ticket,
    Stfalcon\Bundle\EventBundle\Entity\Event,
    Stfalcon\Bundle\EventBundle\Entity\Mail,
    Application\Bundle\UserBundle\Entity\User;
use Application\Bundle\DefaultBundle\Service\PaymentService;
use Symfony\Component\DependencyInjection\Container;
use Zend\Stdlib\DateTime;

/**
 * Class PaymentListener
 * @package Stfalcon\Bundle\EventBundle\EventListener
 */
class PaymentListener
{

    /** @var \Stfalcon\Bundle\EventBundle\Helper\StfalconMailerHelper $mailerHelper */
    private $mailerHelper;

    /** @var \Stfalcon\Bundle\EventBundle\Helper\PdfGeneratorHelper $pdfGeneratorHelper */
    private $pdfGeneratorHelper;

    /** @var \Swift_Mailer $mailer */
    private $mailer;
    /**
     * @var Container
     */
    private $container;

    /**
     * PaymentListener constructor.
     *
     * @param Container $container
     */
    public function __construct($container)
    {
        $this->container = $container;
    }

    /**
     * @param LifecycleEventArgs $args
     *
     * @throws \Exception
     */
    public function postUpdate(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();
        if ($entity instanceof Payment) {
            if (Payment::STATUS_PAID === $entity->getStatus()) {
                $this->mailer = $this->container->get('mailer');
                $this->mailerHelper = $this->container->get('stfalcon_event.mailer_helper');
                $this->pdfGeneratorHelper = $this->container->get('app.helper.new_pdf_generator');

                /** @var PaymentService $paymentService */
                $paymentService = $this->container->get('stfalcon_event.payment.service');
                $paymentService->setTicketsCostAsSold($entity);
                $paymentService->calculateTicketsPromocode($entity);
                /** @var EntityManager $em */
                $em = $this->container->get('doctrine.orm.entity_manager');
                $tickets = $em->getRepository('StfalconEventBundle:Ticket')
                    ->getAllTicketsByPayment($entity);

                /** @var Ticket $ticket */
                foreach ($tickets as $ticket) {
                    /** @var $user User */
                    $user = $ticket->getUser();

                    /** @var Event $event */
                    $event = $ticket->getEvent();

                    $mail = new Mail();
                    $mail->addEvent($event);

                    $html = $this->pdfGeneratorHelper->generateHTML($ticket);
                    $message = $this->mailerHelper->formatMessage($user, $mail, false, true);

                    $message->setSubject($event->getName());
                    $message->attach(
                        \Swift_Attachment::newInstance(
                            $this->pdfGeneratorHelper->generatePdfFile($ticket, $html),
                            $ticket->generatePdfFilename()
                        )
                    );

                    $this->mailer->send($message);
                }
            }
        }
    }
}
