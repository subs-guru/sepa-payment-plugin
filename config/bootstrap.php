<?php

use App\Payments\PaymentGatewayRepository;

//
// Registering SEPA payment handler
//
PaymentGatewayRepository::addHandler('SubsGuru\\SEPA\\Payments\\Gateway\\SEPAPaymentGateway');
