<?php
namespace SubsGuru\SEPA\Mailer;

use SubsGuru\Core\Mailer\PaymentsNotificationsMailer;
use SubsGuru\Core\Model\Entity\Payment;
use SubsGuru\Core\Model\Entity\PaymentMean;
use SubsGuru\Core\Util\Localize;
use Cake\Core\Configure;

class SEPANotificationsMailer extends PaymentsNotificationsMailer
{

    /**
     * Message sent when a payment hits the "error" status
     *
     * @param \SubsGuru\Core\Model\Entity\PaymentMean $paymentMean Instance of the payment mean the payment was made with.
     * @param \SubsGuru\Core\Model\Entity\Payment $payment Instance of the payment we are notifying about.
     * @return void
     */
    public function exported(PaymentMean $paymentMean, Payment $payment)
    {
        $language = Localize::locale(true);

        $invoices = $payment->invoices;
        $this->attachInvoicesPaymentsDoc($invoices);

        $this ->defineBaseConfig($paymentMean, $payment);

        if ($payment->getAmount() > 0) {
            $this
                ->template('SubsGuru/SEPA.' . $language . '/payments/exported-payment', 'SubsGuru/SEPA.' . $language . '/default')
                ->subject(__d('SubsGuru/SEPA', 'Levy notification for your subscription to {0}', Configure::read(CONFIG_KEY . '.service.name')));
        }
    }

    /**
     * No-op : we do not notify the customer when the payment reach the success status : he / she already has been notified using
     * the exported status.
     *
     * @param \SubsGuru\Core\Model\Entity\PaymentMean $paymentMean Instance of the payment mean the payment was made with.
     * @param \SubsGuru\Core\Model\Entity\Payment $payment Instance of the payment we are notifying about.
     * @return void
     */
    public function success(PaymentMean $paymentMean, Payment $payment)
    {
    }
}