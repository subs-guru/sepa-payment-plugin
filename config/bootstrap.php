<?php

use App\Payments\PaymentGatewayRepository;

//
// Registering SEPA payment handler
//
PaymentGatewayRepository::add('SubsGuru\\SEPA\\Payments\\Gateway\\SEPAPaymentGateway');
