<?php

namespace Stfalcon\Bundle\EventBundle\Admin;

use Sonata\AdminBundle\Admin\Admin;
use Sonata\AdminBundle\Admin\AdminInterface;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Route\RouteCollection;
use Knp\Menu\ItemInterface as MenuItemInterface;
use Stfalcon\Bundle\EventBundle\Entity\MailQueue;
use Stfalcon\Bundle\EventBundle\Entity\Mail;

/**
 * Class MailAdmin.
 */
class MailAdmin extends Admin
{
    /**
     * Default values to the datagrid.
     *
     * @var array
     */
    protected $datagridValues = array(
        '_sort_by' => 'id',
        '_sort_order' => 'DESC',
    );

    /**
     * @return array
     */
    public function getBatchActions()
    {
        return array();
    }

    /**
     * @param RouteCollection $collection
     */
    protected function configureRoutes(RouteCollection $collection)
    {
        $collection->add('admin_send', $this->getRouterIdParameter().'/admin-send');
        $collection->add('user_send', 'user-send');
    }

    /**
     * @param ListMapper $listMapper
     */
    protected function configureListFields(ListMapper $listMapper)
    {
        $listMapper
            ->addIdentifier('title')
            ->add('statistic', 'string', array('label' => 'Statistic total/sent/open/unsubscribe'))
            ->add('events')
            ->add('_action', 'actions', array(
                'actions' => array(
                    'edit' => array(),
                    'delete' => array(),
                    'ispremium' => array(
                        'template' => 'StfalconEventBundle:Admin:list__action_adminsend.html.twig',
                    ),
                    'start' => array(
                        'template' => 'StfalconEventBundle:Admin:list__action_start.html.twig',
                    ),
                ),
            ));
    }

    /**
     * @param FormMapper $formMapper
     */
    protected function configureFormFields(FormMapper $formMapper)
    {
        $isEdit = (bool) $this->getSubject()->getId();

        $formMapper
            ->with('General')
            ->add('title')
            ->add('text')
            ->add('events', 'entity', array(
                'class' => 'Stfalcon\Bundle\EventBundle\Entity\Event',
                'multiple' => true,
                'expanded' => false,
                'required' => false,
                'read_only' => $isEdit,
            ))
            ->add('start', null, array('required' => false))
            ->add('wantsVisitEvent', null, ['label' => 'Подписанным на события', 'required' => false])
            ->add('paymentStatus', 'choice', array(
                'choices' => array(
                    'paid' => 'Оплачено',
                    'pending' => 'Не оплачено',
                ),
                'required' => false,
                'read_only' => $isEdit,
            ))
            ->end();
    }

    /**
     * @param Mail $mail
     *
     * @return mixed|void
     */
    public function postPersist($mail)
    {
        $container = $this->getConfigurationPool()->getContainer();

        /** @var \Doctrine\ORM\EntityManager $em */
        $em = $container->get('doctrine')->getManager();
        if ($mail->getEvents()->count() > 0 && $mail->isWantsVisitEvent()) {
            $users =  $em->getRepository('ApplicationUserBundle:User')->getRegisteredUsers($mail->getEvents());
            /* @var $users \Application\Bundle\UserBundle\Entity\User[] */
        } elseif ($mail->getEvents()->count() > 0 || $mail->getPaymentStatus()) {
            $users = $em->getRepository('StfalconEventBundle:Ticket')
                ->findUsersSubscribedByEventsAndStatus($mail->getEvents(), $mail->getPaymentStatus());
        } else {
            $users = $em->getRepository('ApplicationUserBundle:User')->getAllSubscribed();
        }

        if (isset($users)) {
            $countSubscribers = 0;
            foreach ($users as $user) {
                if (filter_var($user->getEmail(), FILTER_VALIDATE_EMAIL)) {
                    $mailQueue = new MailQueue();
                    $mailQueue->setUser($user);
                    $mailQueue->setMail($mail);
                    $em->persist($mailQueue);
                    ++$countSubscribers;
                }
            }
            $mail->setTotalMessages($countSubscribers);
        }

        $em->persist($mail);
        $em->flush();
    }

    /**
     * @param MenuItemInterface $menu       Menu
     * @param string            $action     Action
     * @param AdminInterface    $childAdmin Child admin
     */
    protected function configureSideMenu(MenuItemInterface $menu, $action, AdminInterface $childAdmin = null)
    {
        if (!$childAdmin && !in_array($action, array('edit', 'show'))) {
            return;
        }

        $admin = $this->isChild() ? $this->getParent() : $this;
        $id = $admin->getRequest()->get('id');

        $menu->addChild('Mail', array('uri' => $admin->generateUrl('edit', array('id' => $id))));
        $menu->addChild('Line items', array('uri' => $admin->generateUrl('stfalcon_event.admin.mail_queue.list', array('id' => $id))));
    }
}
