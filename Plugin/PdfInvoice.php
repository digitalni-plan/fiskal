<?php

namespace Trive\Fiskal\Plugin;

use Magento\Sales\Model\Order\Pdf\Invoice as OriginalPdfInvoice;
use Magento\Framework\UrlInterface;
use Endroid\QrCode\QrCode;

class PdfInvoice
{
    const POREZNA_URL = 'https://porezna.gov.hr/rn/?zki=%s&datv=%s&izn=%s';

    /**
     * @var \Trive\Fiskal\Model\InvoiceManagement
     */
    private $invoiceManagement;

    /**
     * @var UrlInterface
     */
    private $url;

    public function __construct(
        \Trive\Fiskal\Model\InvoiceManagement $invoiceManagement,
        UrlInterface $url
    ) {
        $this->invoiceManagement = $invoiceManagement;
        $this->url = $url;
    }

    public function beforeInsertFiskalInfo(OriginalPdfInvoice $subject, $page, $invoice) {
        $fiskalInfos = [];
        $fiskalInvoice = $this->invoiceManagement->getFiskalInvoice($invoice->getId());
        if (
            !$fiskalInvoice
            || !$fiskalInvoice->getZki()
            || !$fiskalInvoice->getJir()
        ) {
            return;
        }

        $fiskalInfos[] = [
            'label' => 'Broj računa',
            'value' => $fiskalInvoice->getInvoiceNumber()
        ];
        $fiskalInfos[] = [
            'label' => 'Vrijeme izdavanja računa',
            'value' => $fiskalInvoice->getFiskalDateTime()
        ];
        $fiskalInfos[] = [
            'label' => 'ZKI',
            'value' => $fiskalInvoice->getZki()
        ];
        $fiskalInfos[] = [
            'label' => 'JIR',
            'value' => $fiskalInvoice->getJir()
        ];

        $subject->y -= 20;
        $lineBlock = ['lines' => [], 'height' => 15];
        foreach ($fiskalInfos as $fiskalInfo) {
            $lineBlock['lines'][] = [
                [
                    'text' => $fiskalInfo['label'] . ':',
                    'feed' => 375,
                    'align' => 'right',
                    'font' => 'bold',
                ],
                [
                    'text' => $fiskalInfo['value'],
                    'feed' => 565,
                    'align' => 'right'
                ]
            ];
        }

        $page = $subject->drawLineBlocks($page, [$lineBlock]);

        $text = sprintf(
            self::POREZNA_URL,
            $fiskalInvoice->getZki(),
            date('Ymd_Hi', strtotime($fiskalInvoice->getFiskalDateTime())),
            number_format($invoice->getBaseGrandTotal(), 2, '', '')
        );

        $qrCode = new QrCode($text);
        $qrCode->setSize(300);
        $qrCode->setWriterByName('png');
        file_put_contents('qr.code.png', $qrCode->writeString());
        $image = \Zend_Pdf_Image::imageWithPath('qr.code.png');
        $page->drawImage($image, 30, $subject->y, 130, $subject->y + 100);
    }
}
