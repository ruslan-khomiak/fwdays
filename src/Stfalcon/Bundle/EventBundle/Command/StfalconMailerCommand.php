<?php

namespace Stfalcon\Bundle\EventBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Stfalcon\Bundle\EventBundle\Entity\Mail;

/**
 * Class StfalconMailerCommand.
 */
class StfalconMailerCommand extends ContainerAwareCommand
{
    /**
     * Set options.
     */
    protected function configure()
    {
        $this
            ->setName('stfalcon:mailer')
            ->setDescription('Send message from queue')
            ->addOption('amount', null, InputOption::VALUE_OPTIONAL, 'Amount of mails which will send per operation. Default 10.')
            ->addOption('host', null, InputOption::VALUE_OPTIONAL, 'Site host. Default frameworksdays.com.');
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int|null|void
     *
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var \Symfony\Component\Routing\RequestContext $context */
        $context = $this->getContainer()->get('router')->getContext();
        $context->setHost('frameworksdays.com');
        $context->setScheme('http');

        $limit = 10;

        if ($input->getOption('amount')) {
            $limit = (int) $input->getOption('amount');
        }

        if ($input->getOption('host')) {
            $context->setHost($input->getOption('host'));
        }

        /** @var $em \Doctrine\ORM\EntityManager */
        $em = $this->getContainer()->get('doctrine.orm.entity_manager');
        /** @var $mailer \Swift_Mailer */
        $mailer = $this->getContainer()->get('mailer');
        /** @var $mailerHelper \Stfalcon\Bundle\EventBundle\Helper\StfalconMailerHelper */
        $mailerHelper = $this->getContainer()->get('stfalcon_event.mailer_helper');
        /** @var $queueRepository \Stfalcon\Bundle\EventBundle\Repository\MailQueueRepository */
        $queueRepository = $em->getRepository('StfalconEventBundle:MailQueue');

        $mailsQueue = $queueRepository->getMessages($limit);

        /* @var $mail Mail */
        foreach ($mailsQueue as $item) {
            $user = $item->getUser();
            $mail = $item->getMail();

            if (!(
                $user &&
                $mail &&
                $user->isEnabled() &&
                $user->isSubscribe() &&
                $user->isEmailExists() &&
                filter_var($user->getEmail(), FILTER_VALIDATE_EMAIL)
            )) {
                $mail->decTotalMessages();
                $em->remove($item);
                $em->flush();
                continue;
            }

            try {
                $message = $mailerHelper->formatMessage($user, $mail);
            } catch (\Exception $e) {
                $this->getContainer()->get('logger')->addError($e->getMessage(), array('email' => $user->getEmail()));

                $mail->decTotalMessages();
                $em->remove($item);
                $em->flush();
                continue;
            }

            $headers = $message->getHeaders();
            $http = $this->getContainer()->get('router')->generate(
                'unsubscribe',
                [
                    'hash' => $user->getSalt(),
                    'userId' => $user->getId(),
                    'mailId' => $mail->getId(),
                ],
                true
            );

            $headers->removeAll('List-Unsubscribe');
            $headers->addTextHeader('List-Unsubscribe', '<'.$http.'>');

            if ($mailer->send($message)) {
                $mail->incSentMessage();
                $item->setIsSent(true);

                $em->flush();
            }
        }
    }
}
