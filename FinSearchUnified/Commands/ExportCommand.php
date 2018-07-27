<?php

namespace FinSearchUnified\Commands;

use FinSearchUnified\ShopwareProcess;
use Shopware\Commands\ShopwareCommand;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ExportCommand extends ShopwareCommand
{
    protected function configure()
    {
        $this->setName('findologic:export')
            ->setDescription('Export Data to Findologic')
            ->addArgument('shopkey', InputArgument::REQUIRED, 'Findologic ShopKey')
            ->addArgument('language', InputArgument::OPTIONAL, 'Shoplanguage', 'de_DE')
            ->setHelp('The <info>%command.name%</info> exports Data to XML Schema for Findologic');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Lade Progressbar
        /** @var ShopwareProcess $blController */
        $blController = $this->container->get('fin_search_unified.shopware_process');
        $shopkey = $input->getArgument('shopkey');
        $language = $input->getArgument('language');

        $output->writeln('Starting export Data to Findologic XML');

        $progress = new ProgressBar($output, count(0));

        $progress->start();
        $blController->setShopKey($shopkey);
        $blController->getFindologicXml($language, 0, 0, true);

        $progress->finish();
        $output->writeln('');
        $output->writeln('Export succesful');
    }
}
