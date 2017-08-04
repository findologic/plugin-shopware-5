<?php

use \FindologicSearch\Components\Findologic\Export;

class Shopware_Controllers_Frontend_Findologic extends Enlight_Controller_Action
{
    /**
     * Executes main export. Validates input, gets products for export, creates XML file, validates created file if
     * query string parameter suggests so and echos generated XML.
     */
    public function indexAction()
    {
        $sentryClient = new Raven_Client('https://32e6f71453264b9e91a152d200b0b005:782b1c51ca2c436386c47e72dd23a848@sentry.io/198285');
        $error_handler = new Raven_ErrorHandler($sentryClient);
        $error_handler->registerExceptionHandler();
        $error_handler->registerErrorHandler();
        $error_handler->registerShutdownFunction();


        $shopKey = $this->Request()->getParam('shopkey', false);
        $start = $this->Request()->getParam('start', false);
        $count = $this->Request()->getParam('count', false);
        $customExportPath = realpath(__DIR__ . '/../../') . '/findologicCustomExport.php';

        if (file_exists($customExportPath)) {
            require_once $customExportPath;

            $customExport = new FindologicCustomExport($shopKey, $start, $count);
            $xml = $customExport->buildXml();
        } else {
            $export = new Export($shopKey, $start, $count);
            $xml = $export->buildXml();
        }

        if ($this->Request()->getParam('validate', false) === 'true') {
            $this->validateXml($xml);
        }

        header('Content-Type: application/xml; charset=utf-8');
        die($xml);
    }

    /**
     * Validates xml against findologic export schema. Uses external schema.
     * If schema is not valid, echoes errors and terminates request.
     *
     * @param string $xml XML string to validate.
     */
    private function validateXml($xml)
    {
        // Enable user error handling
        libxml_use_internal_errors(true);

        $dom = new \DOMDocument();
        $dom->loadXML($xml);
        $path = 'https://raw.githubusercontent.com/FINDOLOGIC/xml-export/master/src/main/resources/findologic.xsd';
        if (!$dom->schemaValidate($path)) {
            $errors = libxml_get_errors();
            foreach ($errors as $error) {
                $return = "<br/>\n";
                switch ($error->level) {
                    case LIBXML_ERR_WARNING:
                        $return .= "<b>Warning $error->code</b>: ";
                        break;
                    case LIBXML_ERR_ERROR:
                        $return .= "<b>Error $error->code</b>: ";
                        break;
                    case LIBXML_ERR_FATAL:
                        $return .= "<b>Fatal Error $error->code</b>: ";
                        break;
                }

                $return .= trim($error->message);
                if ($error->file) {
                    $return .= " in <b>$error->file</b>";
                }

                echo $return . " on line <b>$error->line</b>\n";
            }

            die;
        }
    }
}
