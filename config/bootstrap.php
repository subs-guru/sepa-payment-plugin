<?php
use SubsGuru\Core\Payments\PaymentGatewayRepository;

//
// Registering SEPA payment handler
//
PaymentGatewayRepository::add('SubsGuru\\SEPA\\Payments\\Gateway\\SEPAPaymentGateway');
