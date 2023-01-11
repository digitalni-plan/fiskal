<?php

namespace Trive\Fiskal\Plugin;

use Magento\Sales\Model\Order\Pdf\Invoice as OriginalPdfInvoice;
use Magento\Framework\UrlInterface;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelLow;
use Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeMargin;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Writer\PngWriter;

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

        /** @var \Magento\Sales\Model\Order\Invoice $invoice */

        if (
            $invoice->getOrderCurrencyCode() == 'HRK'
            && $invoice->getBaseCurrencyCode() == 'HRK'
        ) {
            $totalEur = $invoice->getBaseGrandTotal() / 7.53450;
            $totalEur = number_format(round($totalEur, 2), 2, ',', '') . ' €';

            $lineBlock = ['lines' => [], 'height' => 15];
            $lineBlock['lines'][] = [
                [
                    'text' => 'Ukupno (EUR)',
                    'feed' => 475,
                    'align' => 'right',
                    'font_size' => '10',
                    'font' => 'bold',
                ],
                [
                    'text' => $totalEur,
                    'feed' => 565,
                    'align' => 'right',
                    'font_size' => '10',
                    'font' => 'bold'
                ]
            ];
            $lineBlock['lines'][] = [
                [
                    'text' => 'Fiksni tečaj konverzije 1 EUR = 7,53450 HRK',
                    'feed' => 565,
                    'align' => 'right',
                    'font_size' => '9',
                ],
            ];
            $page = $subject->drawLineBlocks($page, [$lineBlock]);
        }

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
        if ($fiskalInvoice->getOperator()) {
            $fiskalInfos[] = [
                'label' => __('Operator'),
                'value' => $fiskalInvoice->getOperator()
            ];
        }

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

        $qrCode = QrCode::create($text)
            ->setEncoding(new Encoding('UTF-8'))
            ->setErrorCorrectionLevel(new ErrorCorrectionLevelLow())
            ->setSize(300)
            ->setMargin(0)
            ->setRoundBlockSizeMode(new RoundBlockSizeModeMargin())
            ->setForegroundColor(new Color(0, 0, 0))
            ->setBackgroundColor(new Color(255, 255, 255));

        $writer = new PngWriter();
        $result = $writer->write($qrCode);
        $result->saveToFile('qr.code.png');
        
        $image = \Zend_Pdf_Image::imageWithPath('qr.code.png');
        $page->drawImage($image, 30, $subject->y, 130, $subject->y + 100);
    }
}
