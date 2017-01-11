<?php
namespace SubsGuru\SEPA\Payments\Gateway;

use App\Model\Entity\Payment;
use App\Model\Entity\PaymentMean;
use App\Model\Entity\PaymentMeanConfig;
use App\Payments\AbstractPaymentGateway;
use Cake\Routing\Router;
use Digitick\Sepa\PaymentInformation;

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
    public static function getName()
    {
        return 'sepa';
    }

    /**
     * {@inheritDoc}
     */
    public function getPrettyName()
    {
        return __d('SubsGuru/SEPA', 'SEPA');
    }

    /**
     * {@inheritDoc}
     */
    public function getShortDescriptionText()
    {
        return __d('SubsGuru/SEPA', 'SEPA payment');
    }

    /**
     * {@inheritDoc}
     */
    public function getPossibleStatuses()
    {
        return array_merge(parent::getPossibleStatuses(), [
            $this->getSuccessStatus() => __d('SubsGuru/SEPA', "Exported")
        ]);
    }

    public function getIntermediateStatuses()
    {
        return [
            'ready' => __d('SubsGuru/SEPA', "Waiting for export"),
            'exported' => __d('SubsGuru/SEPA', "Waiting for payment")
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function getPossibleActions()
    {
        return [
            'export' => [
                'title' => __d('SubsGuru/SEPA', "Export SEPA files"),
                'icon' => 'file',
                'url' => Router::url([
                    'plugin' => 'SubsGuru/SEPA',
                    'controller' => 'Payments',
                    'action' => 'export'
                ])
            ],
            'paid' => [
                'title' => __d('SubsGuru/SEPA', "Set as paid"),
                'icon' => 'check',
                'url' => Router::url([
                    'plugin' => null,
                    'controller' => 'ManualPaymentManagement',
                    'action' => 'set-as-paid'
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
                    'label' => __d('SubsGuru/SEPA', 'SEPA export format'),
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
                        'message' => __d('SubsGuru/SEPA', "IBAN format is incorrect")
                    ]
                ]
            ],
            'force_export_type' => [
                'field' => [
                    'label' => __d('SubsGuru/SEPA', 'Force export type'),
                    'type' => 'select',
                    'required' => true,
                    'options' => [
                        'AUTO' => __d('SubsGuru/SEPA', 'Automatic (not forced)'),
                        PaymentInformation::S_RECURRING => 'recurring (forced)',
                        PaymentInformation::S_FIRST => 'first (forced)'
                    ],
                    'default' => 'AUTO'
                ],
                'help' => __d('SubsGuru/SEPA', 'Use this setting to force default export type')
            ],
            'iban' => [
                'field' => [
                    'label' => __d('SubsGuru/SEPA', 'Creditor account IBAN'),
                    'type' => 'text',
                    'required' => true
                ],
                'validators' => [
                    'format' => [
                        'rule' => [$this, 'validateIBAN'],
                        'message' => __d('SubsGuru/SEPA', "IBAN format is incorrect")
                    ]
                ]
            ],
            'bic' => [
                'field' => [
                    'label' => __d('SubsGuru/SEPA', 'Creditor account BIC'),
                    'type' => 'text',
                    'required' => true
                ],
                'validators' => [
                    'format' => [
                        'rule' => [$this, 'validateBIC'],
                        'message' => __d('SubsGuru/SEPA', "BIC format is incorrect")
                    ]
                ]
            ],
            'ics' => [
                'field' => [
                    'label' => __d('SubsGuru/SEPA', 'Agent ICS'),
                    'type' => 'text',
                    'required' => true
                ]
            ],
            'compagny' => [
                'field' => [
                    'label' => __d('SubsGuru/SEPA', 'Compagny legal name'),
                    'type' => 'text',
                    'required' => true
                ]
            ],
            'siret' => [
                'field' => [
                    'label' => __d('SubsGuru/SEPA', 'Compagny SIRET'),
                    'type' => 'text',
                    'required' => true
                ]
            ],
            'tva' => [
                'field' => [
                    'label' => __d('SubsGuru/SEPA', 'Creditor TVA code'),
                    'type' => 'text',
                    'required' => true
                ]
            ],
            'short_description' => [
                'field' => [
                    'label' => __d('SubsGuru/SEPA', 'Compagny short description'),
                    'type' => 'text',
                    'required' => true
                ]
            ],
            'address' => [
                'field' => [
                    'label' => __d('SubsGuru/SEPA', 'Creditor address'),
                    'type' => 'text',
                    'required' => true
                ]
            ],
            'postalCode' => [
                'field' => [
                    'label' => __d('SubsGuru/SEPA', 'Creditor postal code'),
                    'type' => 'text',
                    'required' => true
                ]
            ],
            'city' => [
                'field' => [
                    'label' => __d('SubsGuru/SEPA', 'Creditor city'),
                    'type' => 'text',
                    'required' => true
                ]
            ],
            'country' => [
                'field' => [
                    'label' => __d('SubsGuru/SEPA', 'Creditor country'),
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
                    'label' => __d('SubsGuru/SEPA', 'Debitor account IBAN country code'),
                    'type' => 'text',
                    'placeholder' => 'ex: FR',
                    'required' => true
                ],
                'validators' => [
                    'length' => [
                        'rule' => ['lengthBetween', 2, 2],
                        'message' => __d('SubsGuru/SEPA', "Country code should be two characters (example: DE, FR)")
                    ]
                ]
            ],
            'iban_key' => [
                'field' => [
                    'label' => __d('SubsGuru/SEPA', 'Debitor account IBAN key'),
                    'type' => 'text',
                    'placeholder' => 'ex: 76',
                    'required' => true
                ],
                'validators' => [
                    'numeric' => [
                        'rule' => ['numeric'],
                        'message' => __d('SubsGuru/SEPA', "IBAN key should be a numerical value")
                    ],
                    'length' => [
                        'rule' => ['lengthBetween', 2, 2],
                        'message' => __d('SubsGuru/SEPA', "IBAN key should be two digits")
                    ]
                ],
                'validators' => [
                    'length' => [
                        'rule' => ['lengthBetween', 2, 2],
                        'message' => __d('SubsGuru/SEPA', "Country code should be two characters (example: DE, FR)")
                    ]
                ]
            ],
            'iban_code' => [
                'field' => [
                    'label' => __d('SubsGuru/SEPA', 'Debitor account IBAN number'),
                    'type' => 'text',
                    'required' => true
                ],
                'displayer' => [$this, 'displayIBAN'],
                'validators' => [
                    'length' => [
                        'rule' => [$this, 'validateIBANCode'],
                        'message' => __d('SubsGuru/SEPA', "IBAN is not valid")
                    ]
                ]
            ],
            'bic' => [
                'field' => [
                    'label' => __d('SubsGuru/SEPA', 'BIC'),
                    'type' => 'text',
                    'required' => true
                ],
                'validators' => [
                    'format' => [
                        'rule' => [$this, 'validateBIC'],
                        'message' => __d('SubsGuru/SEPA', "BIC format is incorrect")
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
     * Full IBAN validator.
     *
     * @param  string $iban Full IBAN to valiate
     * @return bool `true` if valid
     */
    public function validateIBAN($iban)
    {
        return verify_iban(iban_to_machine_format($iban), true) === true;
    }

    /**
     * IBAN code validator.
     *
     * @param  string $iban IBAN code to valiate
     * @return bool `true` if valid
     */
    public function validateIBANCode($ibanCode, $form)
    {
        if (empty($form['data']['sepa_iban_country']) || empty($form['data']['sepa_iban_key'])) {
            // We don't notify user that IBAN is not valid until country code or key are not given.
            // Anyway, validation will fail.
            return true;
        }

        $iban = $form['data']['sepa_iban_country'] . $form['data']['sepa_iban_key'] . $ibanCode;

        return verify_iban($iban, true) === true;
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
