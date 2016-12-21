<?php
namespace SubsGuru\SEPA\Payments\Gateway;

use App\Model\Entity\Payment;
use App\Model\Entity\PaymentMean;
use App\Model\Entity\PaymentMeanConfig;
use App\Payments\AbstractPaymentGateway;
use Cake\Routing\Router;

defined('SEPA_XML_FOLDER')?: define('SEPA_XML_FOLDER', ROOT . '/tmp/sepa');
defined('SEPA_XML_FOLDER_UMASK')?: define('SEPA_XML_FOLDER_UMASK', 0775);

/**
 * Cheque payment handler.
 *
 * @author Julien Cambien
 */
class SEPAPaymentGateway extends AbstractPaymentGateway
{
    /** Default SEPA XML format/version */
    const DEFAULT_PAIN = 'pain.008.001.02';

    /** Status "Ready for export" */
    const STATUS_READY = 'ready';

    /**
     * {@inheritDoc}
     */
    public function getName()
    {
        return 'sepa';
    }

    /**
     * {@inheritDoc}
     */
    public function getPrettyName()
    {
        return __d('payment-sepa', 'SEPA');
    }

    /**
     * {@inheritDoc}
     */
    public function getShortDescriptionText()
    {
        return __d('payment-sepa', 'SEPA payment');
    }

    /**
     * {@inheritDoc}
     */
    public function getPossibleStatuses()
    {
        return array_merge(parent::getPossibleStatuses(), [
            $this->getSuccessStatus() => __d('payment-sepa', "Exported")
        ]);
    }

    public function getIntermediateStatuses()
    {
        return [
            'ready' => __d('payment-sepa', "Waiting for export")
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function getPossibleActions()
    {
        return [
            'export' => [
                'title' => __d('sepa-payment', "Export SEPA files"),
                'icon' => 'file',
                'url' => Router::url([
                    'plugin' => 'SubsGuru/SEPA',
                    'controller' => 'Payments',
                    'action' => 'export'
                ])
            ]
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function getConfigurationFields()
    {
        return [
            'format' => [
                'field' => [
                    'label' => __d('payment-sepa', 'SEPA export format'),
                    'type' => 'select',
                    'required' => true,
                    'options' => [
                        'pain.008.001.02' => 'pain.008.001.02'
                    ],
                    'default' => static::DEFAULT_PAIN
                ],
                'validators' => [
                    'format' => [
                        'rule' => [$this, 'validatePAIN'],
                        'message' => __d('sepa-payment', "IBAN format is incorrect")
                    ]
                ]
            ],
            'iban' => [
                'field' => [
                    'label' => __d('payment-sepa', 'Creditor account IBAN'),
                    'type' => 'text',
                    'required' => true
                ],
                'validators' => [
                    'format' => [
                        'rule' => [$this, 'validateIBAN'],
                        'message' => __d('sepa-payment', "IBAN format is incorrect")
                    ]
                ]
            ],
            'bic' => [
                'field' => [
                    'label' => __d('payment-sepa', 'Creditor account BIC'),
                    'type' => 'text',
                    'required' => true
                ],
                'validators' => [
                    'format' => [
                        'rule' => [$this, 'validateBIC'],
                        'message' => __d('sepa-payment', "BIC format is incorrect")
                    ]
                ]
            ],
            'ics' => [
                'field' => [
                    'label' => __d('payment-sepa', 'Agent ICS'),
                    'type' => 'text',
                    'required' => true
                ]
            ],
            'compagny' => [
                'field' => [
                    'label' => __d('payment-sepa', 'Compagny legal name'),
                    'type' => 'text',
                    'required' => true
                ]
            ],
            'siret' => [
                'field' => [
                    'label' => __d('payment-sepa', 'Compagny SIRET'),
                    'type' => 'text',
                    'required' => true
                ]
            ],
            'tva' => [
                'field' => [
                    'label' => __d('payment-sepa', 'Creditor TVA code'),
                    'type' => 'text',
                    'required' => true
                ]
            ],
            'short_description' => [
                'field' => [
                    'label' => __d('payment-sepa', 'Compagny short description'),
                    'type' => 'text',
                    'required' => true
                ]
            ],
            'address' => [
                'field' => [
                    'label' => __d('payment-sepa', 'Creditor address'),
                    'type' => 'text',
                    'required' => true
                ]
            ],
            'postalCode' => [
                'field' => [
                    'label' => __d('payment-sepa', 'Creditor postal code'),
                    'type' => 'text',
                    'required' => true
                ]
            ],
            'city' => [
                'field' => [
                    'label' => __d('payment-sepa', 'Creditor city'),
                    'type' => 'text',
                    'required' => true
                ]
            ],
            'country' => [
                'field' => [
                    'label' => __d('payment-sepa', 'Creditor country'),
                    'type' => 'text',
                    'required' => true
                ]
            ]
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function getParametersFields()
    {
        return [
            'iban_country' => [
                'field' => [
                    'label' => __d('payment-sepa', 'Debitor account IBAN country code'),
                    'type' => 'text',
                    'placeholder' => 'ex: FR',
                    'required' => true
                ],
                'validators' => [
                    'length' => [
                        'rule' => ['lengthBetween', 2, 2],
                        'message' => __d('payment-sepa', "Country code should be two characters (example: DE, FR)")
                    ]
                ]
            ],
            'iban_key' => [
                'field' => [
                    'label' => __d('payment-sepa', 'Debitor account IBAN key'),
                    'type' => 'text',
                    'placeholder' => 'ex: 76',
                    'required' => true
                ],
                'validators' => [
                    'numeric' => [
                        'rule' => ['numeric'],
                        'message' => __d('payment-sepa', "IBAN key should be a numerical value")
                    ],
                    'length' => [
                        'rule' => ['lengthBetween', 2, 2],
                        'message' => __d('payment-sepa', "IBAN key should be two digits")
                    ]
                ]
            ],
            'iban_code' => [
                'field' => [
                    'label' => __d('payment-sepa', 'Debitor account IBAN number'),
                    'type' => 'text',
                    'required' => true
                ],
                'displayer' => [$this, 'displayIBAN']
            ],
            'bic' => [
                'field' => [
                    'label' => __d('payment-sepa', 'BIC'),
                    'type' => 'text',
                    'required' => true
                ],
                'validators' => [
                    'format' => [
                        'rule' => [$this, 'validateBIC'],
                        'message' => __d('sepa-payment', "BIC format is incorrect")
                    ]
                ]
            ]
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function validateConfiguration(array $config)
    {
        if (!isset($config['iban'])) {
            return false;
        }

        $iban = iban_to_machine_format($config['iban']);

        if (!verify_iban($iban, true)) {
            return false;
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function validateParameters(array $parameters)
    {
        if (empty($parameters['iban_country']) || empty($parameters['iban_key']) || empty($parameters['iban_code']) ) {
            return false;
        }
        $iban = iban_to_machine_format($parameters['iban_country'] . $parameters['iban_key'] . $parameters['iban_code']);

        if (!verify_iban($iban, true)) {
            return false;
        }

        if (empty($parameters['bic']) || !$this->validateBIC($parameters['bic'])) {
            return false;
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function isManualProcessing()
    {
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function doPayment(Payment $payment, array $config, array $parameters, $amount, $currency, $recurrent = false)
    {
        // Full IBAN check
        $iban = iban_to_machine_format(
            $parameters['iban_country'] . $parameters['iban_key'] . $parameters['iban_code']
        );

        if (!verify_iban($iban, true)) {
            return $this->reportError("IBAN validation has failed for '{$iban}'");
        }

        $payment->updateStatus(static::STATUS_READY);
    }

    /**
     * {@inheritDoc}
     */
    public function onCreate(PaymentMean $paymentMean, array $form, array $options = [])
    {
        $paymentMean->formToParameters($form);
    }

    /**
     * {@inheritDoc}
     */
    public function onConfigure(PaymentMeanConfig $config)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function onParameterize(PaymentMean $paymentMean)
    {
    }

    /**
     * Method used to display IBAN on screen respecting customer privacy.
     *
     * @param string $ibanCode IBAN to obfuscate
     * @return string Obfuscated IBAN
     */
    public function displayIBAN($ibanCode)
    {
        if (empty($ibanCode)) {
            return null;
        }

        $length = 6;

        return substr($ibanCode, 0, $length)
             . str_repeat('*', strlen($ibanCode) - $length - 2)
             . substr($ibanCode, -2);
    }

    /**
     * IBAN validator.
     *
     * @param  string $iban IBAN to valiate
     * @return bool `true` if valid
     */
    public function validateIBAN($iban)
    {
        return verify_iban(iban_to_machine_format($iban), true) === true;
    }

    /**
     * BIC validator.
     *
     * @param  string $bic BIC to valiate
     * @return bool `true` if valid
     */
    public function validateBIC($bic)
    {
        $pattern = "([a-zA-Z]{4}[a-zA-Z]{2}[a-zA-Z0-9]{2}([a-zA-Z0-9]{3})?)";

        return preg_match("/^{$pattern}\$/", $bic) > 0;
    }

    /**
     * Validate XML format version.
     *
     * @param  string $pain version
     * @return bool `true` if recognized
     */
    public function validatePAIN($pain)
    {
        return in_array($pain, [
            'pain.008.001.03', 'pain.008.001.02'
        ]);
    }
}
