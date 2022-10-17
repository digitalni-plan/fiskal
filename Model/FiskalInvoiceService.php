<?php
/**
 * Trive Fiskal API Library.
 *
 * @category  Trive
 * @package   Trive_Fiskal
 * @copyright 2017 Trive d.o.o (http://trive.digital)
 * @license   http://www.opensource.org/licenses/mit-license.php The MIT License
 * @link      http://trive.digital
 */

namespace Trive\Fiskal\Model;

use Trive\Fiskal\Api\Data\InvoiceInterface as FiskalInvoiceInterface;
use Trive\Fiskal\Api\Data\InvoiceInterfaceFactory;
use Trive\Fiskal\Api\InvoiceRepositoryInterface;
use Magento\Sales\Api\Data\InvoiceInterface;
use Magento\Sales\Api\Data\CreditmemoInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;

class FiskalInvoiceService
{
    /** @var Config */
    protected $config;

    /** @var InvoiceInterfaceFactory */
    protected $invoiceDataFactory;

    /** @var InvoiceRepositoryInterface */
    protected $invoiceRepository;

    /** @var DateTime */
    protected $dateTime;

    /**
     * FiskalInvoiceService constructor.
     *
     * @param Config                     $config
     * @param InvoiceInterfaceFactory    $invoiceDataFactory
     * @param InvoiceRepositoryInterface $invoiceRepository
     * @param DateTime                   $dateTime
     */
    public function __construct(
        Config $config,
        InvoiceInterfaceFactory $invoiceDataFactory,
        InvoiceRepositoryInterface $invoiceRepository,
        DateTime $dateTime
    ) {
        $this->config = $config;
        $this->invoiceDataFactory = $invoiceDataFactory;
        $this->invoiceRepository = $invoiceRepository;
        $this->dateTime = $dateTime;
    }

    /**
     * Create fiskal invoice from invoice
     *
     * @param InvoiceInterface $invoice
     */
    public function createFiskalInvoiceFromInvoice($invoice)
    {
        /** @var \Trive\Fiskal\Model\Invoice $fiskalInvoice */
        $fiskalInvoice = $this->invoiceDataFactory->create();
        $fiskalInvoice->setStoreId($invoice->getStoreId())
                      ->setCreatedAt($this->dateTime->gmtDate())
                      ->setLocationCode($this->config->getLocationCode())
                      ->setPaymentDeviceCode($this->config->getPaymentDeviceCode())
                      ->setEntityType(FiskalInvoiceInterface::ENTITY_TYPE_INVOICE)
                      ->setEntityId($invoice->getEntityId())
                      ->setOperator($this->config->getOperator());
        try {
            $this->invoiceRepository->save($fiskalInvoice);
        } catch (\Exception $e) {
        }
    }

    /**
     * Create refund fiskal invoice from creditmemo
     *
     * @param CreditmemoInterface $creditmemo
     */
    public function createRefundFiskalInvoiceFromCreditmemo($creditmemo)
    {
        $fiskalInvoice = $this->invoiceDataFactory->create();
        $fiskalInvoice->setStoreId($creditmemo->getStoreId())
                      ->setLocationCode($this->config->getLocationCode())
                      ->setPaymentDeviceCode($this->config->getPaymentDeviceCode())
                      ->setEntityType(FiskalInvoiceInterface::ENTITY_TYPE_CREDITMEMO)
                      ->setEntityId($creditmemo->getEntityId())
                      ->setOperator($this->config->getOperator());
        try {
            $this->invoiceRepository->save($fiskalInvoice);
        } catch (\Exception $e) {
        }
    }
}
