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

namespace Trive\Fiskal\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Trive\Fiskal\Api\InvoiceRepositoryInterface;
use Trive\Fiskal\Model\Config;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Event\Observer;
use Trive\Fiskal\Api\Data\InvoiceInterface;

/**
 * @SuppressWarnings(PHPMD.LongVariable)
 */
class EmailInvoiceSetTemplateVarsBefore implements ObserverInterface
{
    /**
     * @param InvoiceRepositoryInterface $fiskalInvoiceRepository
     */
    protected $fiskalInvoiceRepository;

    /**
     * @var \Magento\Framework\Api\SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;

    /**
     * @var \Magento\Framework\Api\FilterBuilder
     */
    protected $filterBuilder;

    /**
     * Store config
     *
     * @var Config
     */
    protected $config;

    /**
     * Store manager
     *
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * EmailInvoiceSetTemplateVarsBefore constructor.
     *
     * @param FilterBuilder              $filterBuilder
     * @param SearchCriteriaBuilder      $searchCriteriaBuilder
     * @param InvoiceRepositoryInterface $fiskalInvoiceRepository
     * @param Config                     $config
     * @param StoreManagerInterface      $storeManager
     */
    public function __construct(
        FilterBuilder $filterBuilder,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        InvoiceRepositoryInterface $fiskalInvoiceRepository,
        Config $config,
        StoreManagerInterface $storeManager
    ) {
        $this->filterBuilder = $filterBuilder;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->fiskalInvoiceRepository = $fiskalInvoiceRepository;
        $this->config = $config;
        $this->storeManager = $storeManager;
    }

    /**
     * Add fiskal template variables to creditmemo
     *
     * @param Observer $observer
     *
     * @return $this
     */
    public function execute(Observer $observer)
    {
        $storeId = $this->storeManager->getStore()->getId();
        $this->config->setStoreId($storeId);
        if (!$this->config->isEnabled($storeId)) {
            return $this;
        }

        $event = $observer->getEvent();
        $transport = $event->getTransport();
        $invoice = $transport['invoice'];

        $fiskalInvoiceData = $this->getFiskalInvoiceData($invoice->getId());
        $invoice->addData($fiskalInvoiceData);

        return $this;
    }

    /**
     * Prepare fiskal invoice data to use in variables
     *
     * @param $identifier
     *
     * @return array
     */
    private function getFiskalInvoiceData($identifier)
    {
        $fiskalInvoice = $this->getFiskalInvoice($identifier);

        $data = [];
        if (!isset($fiskalInvoice[InvoiceInterface::ID]) || !isset($fiskalInvoice[InvoiceInterface::SYNCED_AT])) {
            return $data;
        }
        $data = [
            'fiskal_'.InvoiceInterface::INVOICE_NUMBER => $fiskalInvoice[InvoiceInterface::INVOICE_NUMBER],
            'fiskal_fiskal_date_time'                  => $fiskalInvoice[InvoiceInterface::FISKAL_DATE_TIME],
            'fiskal_'.InvoiceInterface::SYNCED_AT      => $fiskalInvoice[InvoiceInterface::SYNCED_AT],
            'fiskal_'.InvoiceInterface::JIR            => $fiskalInvoice[InvoiceInterface::JIR],
            'fiskal_'.InvoiceInterface::ZKI            => $fiskalInvoice[InvoiceInterface::ZKI],
        ];

        return $data;
    }

    /**
     * Get fiskal invoice by identifier
     *
     * @param int $identifier
     *
     * @return InvoiceInterface[]
     */
    private function getFiskalInvoice($identifier)
    {
        $this->searchCriteriaBuilder->addFilter(
            InvoiceInterface::ENTITY_TYPE,
            InvoiceInterface::ENTITY_TYPE_INVOICE
        )->addFilter(
            InvoiceInterface::ENTITY_ID,
            $identifier
        )->addFilter(
            InvoiceInterface::SYNCED_AT,
            null,
            'notnull'
        );

        $searchCriteria = $this->searchCriteriaBuilder->create();
        $searchResults = $this->fiskalInvoiceRepository->getList($searchCriteria)->getItems();
        $fiskalInvoice = array_shift($searchResults);

        return $fiskalInvoice;
    }
}
