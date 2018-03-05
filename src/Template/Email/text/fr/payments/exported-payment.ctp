<?php
    $amount = $payment->amount;
    if (!empty($payment->invoices)):
        $paymentInvoices = new \Cake\Collection\Collection($payment->invoices);
        $invoicesList = $paymentInvoices->extract(function($invoice) {
            return $invoice->full_number;
        })->toArray();
        $typeDocument = in_array($paymentInvoices->first()->type, \SubsGuru\Core\Model\Entity\Invoice::TYPES_CREDIT_NOTES) ? 'Avoir' : 'Facture';

        if ($typeDocument == 'Avoir') {
            $amount = $payment->amount * -1;
        }

        if (count($invoicesList) > 1) {
            $typeDocument .= 's';
        }

        $balanceDueInvoices = $this->Emails->getBalanceDue($payment);
    endif;
?>

Bonjour,

Nous venons de déposer en banque la demande de prélèvement sur le compte bancaire indentifié dans votre compte pour le paiement pour votre abonnement à <?= \Cake\Core\Configure::read(CONFIG_KEY . '.service.name'); ?>.

Vous trouverez ci après le détail de votre paiement.

<?php if (isset($invoicesList)): ?>
    <?php echo $typeDocument; ?> : <?= \Cake\Utility\Text::toList($invoicesList); ?>
<?php endif; ?>

Montant : <?= \SubsGuru\Core\Util\Money::money($amount, $payment->currency); ?>

Moyen de paiement : <?= $payment->getPaymentGateway()->getBillingName(); ?>

<?php $lastPaymentStatus = end($payment->payment_statuses); ?>
Date : <?= $lastPaymentStatus->created; ?>

Motif : <?= nl2br($lastPaymentStatus->payment_mean_infos); ?>

<?php if (!empty($balanceDueInvoices)): ?>
    <?php foreach ($balanceDueInvoices as $invoiceNum => $balanceDue): ?>
Restant dû <?= $invoiceNum ?> : <?= \SubsGuru\Core\Util\Money::money($balanceDue, $payment->currency); ?>
    <?php endforeach; ?>
<?php endif; ?>