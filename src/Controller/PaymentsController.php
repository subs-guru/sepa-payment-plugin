<?php
namespace SubsGuru\SEPA\Controller;

use App\Controller\AbstractPaymentGatewayController;
use Cake\Core\Configure;
use Cake\I18n\Time;
use Cake\Network\Exception\InternalErrorException;
use Cake\ORM\TableRegistry;
use Digitick\Sepa\PaymentInformation;
use Digitick\Sepa\TransferFile\Factory\TransferFileFacadeFactory;
use SubsGuru\SEPA\Payments\Gateway\SEPAPaymentGateway;
use ZipArchive;

class PaymentsController extends AbstractPaymentGatewayController
{
    /**
     * Export form.
     *
     * @return Cake\Network\Response
     */
    public function export()
    {
        $payments = $this->queryPayments();

        $ignored = clone $payments;
        $ignored->where(['PaymentMeans.type !=' => $this->getPaymentGateway()->getName()]);

        $filtered = clone $payments;
        $filtered->where(['PaymentMeans.type' => $this->getPaymentGateway()->getName()]);

        // Handling POST
        if ($this->request->is('post')) {
            return $this->doExport($filtered);
        }

        $debitsTypes = $this->getDebitsTypes();
        $paymentsCounters = $this->getPaymentsCounters($payments);

        foreach ($filtered as $payment) {
            $payment->set('sepa_type', $this->detectSepaType($payment, $paymentsCounters));
        }

        $this->set('ignored', $ignored);
        $this->set('payments', $filtered);
        $this->set('paymentsCounters', $paymentsCounters);
        $this->set('debits', $debitsTypes);
        $this->set('debitsTypes', array_flip($debitsTypes));
    }

    /**
     * Export processing (POST ONLY).
     *
     * @param  Cake\ORM\Query $payments Payments query
     * @return Cake\Network\Response
     */
    protected function doExport($payments)
    {
        $selection = $this->getSelection();
        $config = $this->getPaymentGateway()->getConfiguration()->propertiesToArray();
        $paymentsCounters = $this->getPaymentsCounters($payments);
        $sepaDocuments = [];

        // Creating all XML nodes for each payment
        foreach ($payments as $payment) {
            $paymentName = str_replace('-', '', $payment->id);

            if (!isset($this->request->data['type-' . $payment->id])) {
                $payment->__ignore = true;
                continue;
            }

            $type = strtoupper($this->request->data['type-' . $payment->id]);
            $payment->__ignore = false;
            $parameters = $payment->payment_mean->getParameters();

            $iban = iban_to_machine_format($parameters['iban_country'] . $parameters['iban_key'] . $parameters['iban_code']);

            if ($type == 'AUTO') { //@TODO
                $type = $this->detectSepaType($payment, $paymentsCounters);
            }

            // Creating SEPA document for current type
            $documentName = md5(implode(',', $this->getSelection()));

            if (!isset($sepaDocuments[$type])) {
                $sepaDocuments[$type] = TransferFileFacadeFactory::createDirectDebit(
                    $documentName,
                    $config['compagny'],
                    $config['format']
                );
            }

            // Payment creditor informations
            $sepaDocuments[$type]->addPaymentInfo($paymentName, [
                'id'                    => $paymentName,
                'creditorName'          => $config['compagny'],
                'creditorAccountIBAN'   => strtoupper($config['iban']),
                'creditorAgentBIC'      => strtoupper(str_pad($config['bic'], 11, 'X')),
                'creditorId'            => $config['ics'],
                'seqType'               => $type
            ]);

            $debtorName = (!empty($payment->payment_mean->customer->org_legal_name))
                ? $payment->payment_mean->customer->org_legal_name
                : $payment->payment_mean->customer->org_business_name;

            // Payment debitor informations
            $sepaDocuments[$type]->addTransfer($paymentName, [
                'amount'                => $payment->getAmount(),
                'debtorIban'            => strtoupper($iban),
                'debtorBic'             => strtoupper(str_pad($parameters['bic'], 11, 'X')),
                'debtorName'            => $debtorName,
                'debtorMandate'         => $payment->payment_mean->customer->created,
                'debtorMandateSignDate' => (!empty($parameters['mandate_sign_date'])) ? $parameters['mandate_sign_date'] : '2014-02-01',
                'remittanceInformation' => $payment->id
            ]);
        }

        // Validating created documents
        $sepaXml = array();

        foreach ($sepaDocuments as $sepaType => $sepaDocument) {
            if (!$this->validateDocument($xml = $sepaDocument->asXml())) {
                throw new \Exception("Cannot validate XML document for type {$sepaType} (format: {$config['format']})");
            } else {
                $sepaXml[$sepaType] = $xml;
            }
        }

        // Exporting file for only one type OR group them into a ZIP file.
        if (!empty($this->request->data['type'])) {
            // Creating XML file
            $type = $this->request->data['type'];
            $file = SEPA_XML_FOLDER . '/sepa-exports-' . strtoupper($this->request->data['type']) . '-' . date('Y-m-d-H\hh') . '.xml';

            $this->createTempDir($file);

            if (!isset($sepaXml[$type])) {
                $this->Flash->warning(__d('SubsGuru/SEPA', "No document matched selected type ({0}), nothing to export.", $type));
                return $this->redirect($this->referer());
            }

            file_put_contents($file, $sepaXml[$type]);

            // HTTP response to download file
            $this->response->header('Content-Type', 'text/xml');
            $this->response->header('Content-Length', filesize($file));
            $this->response->header('Content-Disposition', 'attachment; filename="' . basename($file) . '"');
        } else {
            // Creating ZIP file
            $file = SEPA_XML_FOLDER . '/sepa-exports-' . date('Y-m-d-H\hh') . '-' . uniqid() .'.zip';

            $this->createTempDir($file);

            $zip = new ZipArchive();

            if ($zip->open($file, ZipArchive::CREATE) !== true) {
                throw new InternalErrorException("Cannot create ZIP file");
            }

            foreach ($sepaXml as $type => $xml) {
                $zip->addFromString($type . '.xml', $xml);
            }

            $zip->close();

            // HTTP response to download file
            $this->response->header('Content-Type', 'application/zip');
            $this->response->header('Content-Length', filesize($file));
            $this->response->header('Content-Disposition', 'attachment; filename="' . basename($file) . '"');
        }

        // Streaming file
        $this->response->body(function () use ($file, $payments) {
            readfile($file);
            unlink($file);

            foreach ($payments as $payment) {
                if ($payment->__ignore === true) {
                    continue;
                }

                $payment->updateStatus(
                    'exported',
                    __d('SubsGuru/SEPA', "Exported into file `{0}`", basename($file)),
                    ['filename' => basename($file)],
                    true
                );

                TableRegistry::get('Payments')->save($payment);
            }
        });

        return $this->response;
    }

    /**
     * Create temporary directory for file export.
     *
     * @param  string $file File name
     * @return void
     */
    private function createTempDir($file)
    {
        if (!is_dir($dir = dirname($file))) {
            if (!(@mkdir($dir, SEPA_XML_FOLDER_UMASK))) {
                throw new InternalErrorException("Cannot create temporary directory for file export : " . $dir);
            }
        }
    }

    /**
     * Return SEPA debit types.
     *
     * @return array
     */
    private function getDebitsTypes()
    {
        return [
            PaymentInformation::S_FIRST => 'first',
            PaymentInformation::S_RECURRING => 'recurring',
            PaymentInformation::S_FINAL => 'final'
        ];
    }

    /**
     * Return payments counters for each implicated payments means in selection.
     *
     * @return array key/value pairs (payment mean id / count)
     */
    private function getPaymentsCounters($payments)
    {
        $paymentMeans = [];

        foreach ($payments as $payment) {
            if (!in_array($payment->payment_mean_id, $paymentMeans)) {
                $paymentMeans[] = $payment->payment_mean_id;
            }
        }

        $paymentsCounters = TableRegistry::get('PaymentMeans')->find('list', [
            'keyField' => 'id',
            'valueField' => 'count'
        ]);
        $paymentsCounters->leftJoinWith('Payments.PaymentStatuses');
        $paymentsCounters->select(['id', 'count' => $paymentsCounters->func()->count('Payments.id')]);
        $paymentsCounters->where([
            'PaymentMeans.id IN' => $paymentMeans,
            'PaymentStatuses.name =' => $this->getPaymentGateway()->getSuccessStatus()
        ]);
        $paymentsCounters->group('PaymentMeans.id');
        $paymentsCounters->autoFields(true);

        return $paymentsCounters->toArray();
    }

    /**
     * Automaticly detect SEPA export type for passed payment.
     *
     * @param  App\Model\Entity\Payment $payment Payment to detect type on
     * @param  array $paymentsCounters Payment counters list
     * @return string SEPA export type
     */
    private function detectSepaType($payment, array $paymentsCounters)
    {
        static $firsts = array();

        $gateway = $this->getPaymentGateway();

        if (($forcedType = $gateway->getConfiguration()->getProperty('force_export_type')) != 'AUTO') {
            return $forcedType;
        }

        if (in_array($payment->payment_mean_id, $firsts)) {
            return PaymentInformation::S_RECURRING;
        }

        if (!isset($paymentsCounters[$payment->payment_mean_id]) || $paymentsCounters[$payment->payment_mean_id] < 1) {
            $firsts[] = $payment->payment_mean_id;

            return PaymentInformation::S_FIRST;
        }

        return PaymentInformation::S_RECURRING;
    }

    private function validateDocument($xml, $pain = SEPAPaymentGateway::DEFAULT_PAIN)
    {
        $domdoc = new \DOMDocument();
        $domdoc->loadXML($xml);

        $validationFile = (is_file($pain)) ? $pain : dirname(__DIR__) . '/' . $pain . '.xsd';

        return $domdoc->schemaValidate($validationFile);
    }
}
