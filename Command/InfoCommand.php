<?php
namespace NathanDay\CatalogImagesClean\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\Console\Cli;

class InfoCommand extends AbstractCommand
{
    /**
     * Configure Function
     *
     * Set Command Name and Options
     */
    public function configure()
    {
        $this->setName('catalog:images:info')
            ->setDescription('Information about Unused and/or Missing Images')
            ->addOption(
                self::INPUT_KEY_UNUSED,
                'u',
                InputOption::VALUE_NONE,
                'Info on unused product images'
            )->addOption(
                self::INPUT_KEY_MISSING,
                'm',
                InputOption::VALUE_NONE,
                'Info on missing product images'
            );

        parent::configure();
    }

    /**
     * Execute Function
     *
     * Entry point to catalog:images:info command
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null
     * @throws \Magento\Framework\Exception\FileSystemException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('<fg=green;options=bold>======================================</>');
        $output->writeln('<fg=green;options=bold>Catalog Product Image Information</>');
        $output->writeln('<fg=green;options=bold>======================================</>');
        $output->writeln('<info>' . $this->getDatabaseProductImageCount() . ' Unique Images in Database</info>');
        $output->writeln('<info>' . $this->getPhysicalProductImageCount() . ' Images in Filesystem</info>');

        if (
            $input->getOption(self::INPUT_KEY_MISSING)
            || $input->getOption(self::INPUT_KEY_UNUSED)
        ) {
            if ($input->getOption(self::INPUT_KEY_MISSING)) {
                $this->executeMissingImages($output);
            }

            if ($input->getOption(self::INPUT_KEY_UNUSED)) {
                $this->executeUnusedImages($output);
            }
        } else {
            $this->executeMissingImages($output);
            $this->executeUnusedImages($output);
        }

        return Cli::RETURN_SUCCESS;
    }

    /**
     * Execute Missing Images Information Logic
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @throws \Magento\Framework\Exception\FileSystemException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function executeMissingImages(OutputInterface $output)
    {
        $output->writeln('<info>' . $this->getMissingProductImageCount() . ' Missing Images</info>');

        if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
            if ($this->getMissingProductImageCount() > 0) {
                $missingImages = array_unique($this->getMissingProductImages());
                $longestString = max(array_map('strlen', $missingImages));

                $output->writeln('+-' . str_pad('', $longestString, '-') . '-+------+');
                $output->writeln('| ' . str_pad('Filename', $longestString) . ' | Uses |');
                $output->writeln('+-' . str_pad('', $longestString, '-') . '-+------+');
                foreach ($missingImages as $imagePath) {
                    $uses = $this->gallery->countImageUses($imagePath);
                    $output->writeln('| ' . str_pad($imagePath, $longestString) . ' | ' . str_pad($uses, 4) . ' |');
                }
                $output->writeln('+-' . str_pad('', $longestString, '-') . '-+------+');
            }
        }
    }

    /**
     * Execute Unused Images Information Logic
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @throws \Magento\Framework\Exception\FileSystemException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function executeUnusedImages(OutputInterface $output)
    {
        $output->writeln('<info>' . $this->getUnusedProductImageCount() . ' Unused Images</info>');

        if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
            if ($this->getUnusedProductImageCount() > 0) {
                $unusedImages = $this->getUnusedProductImages();
                $longestString = max(array_map('strlen', $unusedImages));

                $output->writeln('+-' . str_pad('', $longestString, '-') . '-+');
                $output->writeln('| ' . str_pad('Filename', $longestString) . ' |');
                $output->writeln('+-' . str_pad('', $longestString, '-') . '-+');

                foreach ($unusedImages as $image) {
                    $output->writeln('| ' . str_pad($image, $longestString) . ' |');
                }

                $output->writeln('+-' . str_pad('', $longestString, '-') . '-+');
            }
        }
    }
}
