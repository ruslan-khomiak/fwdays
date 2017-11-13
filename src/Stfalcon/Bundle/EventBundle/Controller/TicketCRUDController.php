<?php

namespace Stfalcon\Bundle\EventBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Sonata\AdminBundle\Controller\CRUDController;
use Stfalcon\Bundle\EventBundle\Entity\Payment;
use Stfalcon\Bundle\EventBundle\Entity\Ticket;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class TicketCRUDController
 */
class TicketCRUDController extends CRUDController
{
    /**
     * @param int $id
     *
     * @Security("has_role('ROLE_ADMIN')")
     *
     * @return RedirectResponse
     */
    public function removePaidTicketFromPaymentAction($id)
    {
        /** @var Ticket $object */
        $object = $this->admin->getSubject();

        if (!$object) {
            throw new NotFoundHttpException(sprintf('unable to find the object with id : %s', $id));
        }
        /**
         * @var Payment $payment
         */
        if ($object instanceof Ticket && $payment = $object->getPayment() && $payment->isPaid()) {
            $payment->removePaidTicket($object);
        }
        $em = $this->getDoctrine()->getManager();
        $em->flush();
        $this->addFlash('sonata_flash_success', 'Ticket removed successfully');

        return new RedirectResponse($this->admin->generateUrl('list'));
    }
}
