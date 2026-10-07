<?php
declare(strict_types=1);

namespace Italgres\DemoMode\Console\Command;

use Italgres\DemoMode\Model\DemoInstaller;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class InstallDemo extends Command
{
    public function __construct(
        private readonly DemoInstaller $installer,
        private readonly State $appState
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('italgres:demo:install')
            ->setDescription('Install the Italgres 3D configurator demo: materials, 3D models, products and store settings (re-runnable).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->appState->setAreaCode(Area::AREA_ADMINHTML);
        } catch (\Magento\Framework\Exception\LocalizedException) {
            // area already set
        }
        $this->installer->install(static fn(string $line) => $output->writeln('  ' . $line));
        $output->writeln('<info>Demo installed. Run bin/magento cache:flush and open the landing product.</info>');

        return Command::SUCCESS;
    }
}
