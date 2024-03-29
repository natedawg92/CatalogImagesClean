<?php

namespace NathanDay\CatalogImagesClean\Command;

use Magento\Framework\Console\Cli;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class InfoCommand extends AbstractCommand
{
    /** Constants */
    const INPUT_KEY_DATABASE = 'database';
    const INPUT_KEY_PHYSICAL = 'physical';

    protected $functionMap = [
        self::INPUT_KEY_DATABASE => 'executeDatabaseImages',
        self::INPUT_KEY_PHYSICAL => 'executePhysicalImages',
        self::INPUT_KEY_MISSING => 'executeMissingImages',
        self::INPUT_KEY_UNUSED => 'executeUnusedImages',
        self::INPUT_KEY_DUPLICATE => 'executeDuplicateImages',
    ];

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
             )
             ->addOption(
                 self::INPUT_KEY_MISSING,
                 'm',
                 InputOption::VALUE_NONE,
                 'Info on missing product images'
             )
             ->addOption(
                 self::INPUT_KEY_DUPLICATE,
                 't',
                 InputOption::VALUE_NONE,
                 'Info on duplicate product images'
             )
             ->addOption(
                 self::INPUT_KEY_DATABASE,
                 'd',
                 InputOption::VALUE_NONE,
                 'Info on Product images in the Database'
             )
             ->addOption(
                 self::INPUT_KEY_PHYSICAL,
                 'p',
                 InputOption::VALUE_NONE,
                 'Info on Product images in the filesystem'
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
     *
     * @return int
     * @throws FileSystemException
     * @throws LocalizedException
     */
    public function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        $output->writeln('<fg=green;options=bold>======================================</>');
        $output->writeln('<fg=green;options=bold>Catalog Product Image Information</>');
        $output->writeln('<fg=green;options=bold>======================================</>');

        $executed = false;

        foreach ($this->functionMap as $key => $function) {
            if ($input->getOption($key)) {
                $this->{$function}($output);
                $executed = true;
            }
        }

        if (!$executed) {
            foreach ($this->functionMap as $function) {
                $this->{$function}($output);
            }
        }

        return Cli::RETURN_SUCCESS;
    }

    /**
     * Execute Database Images Information Logic
     *
     * @param OutputInterface $output
     *
     * @throws LocalizedException
     */
    protected function executeDatabaseImages(
        OutputInterface $output
    ) {
        $databaseImageCount = $this->getDatabaseProductImageCount();
        $output->writeln('<info>' . $databaseImageCount . ' Unique Images in Database</info>');

        if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE && $databaseImageCount > 0) {
            $databaseImages = array_unique($this->getDatabaseProductImages());
            $longestString = max(array_map('strlen', $databaseImages));

            $output->writeln('+-' . str_pad('', $longestString, '-') . '-+------+');
            $output->writeln('| ' . str_pad('Filename', $longestString) . ' | Uses |');
            $output->writeln('+-' . str_pad('', $longestString, '-') . '-+------+');

            foreach ($databaseImages as $imagePath) {
                $uses = $this->gallery->countImageUses($imagePath);
                $output->writeln('| ' . str_pad($imagePath, $longestString) . ' | ' . str_pad($uses, 4) . ' |');
            }

            $output->writeln('+-' . str_pad('', $longestString, '-') . '-+------+');
        }
    }

    /**
     * @param OutputInterface $output
     *
     * @throws FileSystemException
     */
    protected function executePhysicalImages(
        OutputInterface $output
    ) {
        $physicalImageCount = $this->getPhysicalProductImageCount();
        $output->writeln('<info>' . $physicalImageCount . ' Images in Filesystem</info>');

        if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE && $physicalImageCount > 0) {
            $physicalImages = array_keys($this->getPhysicalProductImages());
            $longestString = max(array_map('strlen', $physicalImages));

            $output->writeln('+-' . str_pad('', $longestString, '-') . '-+');
            $output->writeln('| ' . str_pad('Filename', $longestString) . ' |');
            $output->writeln('+-' . str_pad('', $longestString, '-') . '-+');

            foreach ($physicalImages as $image) {
                $output->writeln('| ' . str_pad($image, $longestString) . ' |');
            }

            $output->writeln('+-' . str_pad('', $longestString, '-') . '-+');
        }
    }

    /**
     * Execute Missing Images Information Logic
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @throws FileSystemException
     * @throws LocalizedException
     */
    protected function executeMissingImages(
        OutputInterface $output
    ) {
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
     * @param OutputInterface $output
     *
     * @throws FileSystemException
     * @throws LocalizedException
     */
    protected function executeUnusedImages(
        OutputInterface $output
    ) {
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

    /**
     * Execute Duplicate Images Information Logic
     *
     * @param OutputInterface $output
     *
     * @throws FileSystemException
     * @throws LocalizedException
     */
    protected function executeDuplicateImages(
        OutputInterface $output
    ) {
        $output->writeln('<info>' . $this->getDuplicateProductImageCount() . ' Duplicate Images</info>');

        if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE
            && $this->getDuplicateProductImageCount() > 0
        ) {
            $imagePaths = [];
            $duplicateImages = $this->getDuplicateProductImages();
            array_walk_recursive(
                $duplicateImages,
                function ($a) use (&$imagePaths) {
                $imagePaths[] = $a;
                }
            );
            $longestString = max(array_map('strlen', array_merge($imagePaths, ['Duplicate of'])));

            $output->writeln(
                '+-'
                . str_pad('', $longestString, '-')
                . '-+-'
                . str_pad('', $longestString, '-')
                . '-+'
            );
            $output->writeln(
                '| '
                . str_pad('Filename', $longestString)
                . ' | '
                . str_pad('Duplicate of', $longestString)
                . ' |'
            );
            $output->writeln(
                '+-'
                . str_pad('', $longestString, '-')
                . '-+-'
                . str_pad('', $longestString, '-')
                . '-+'
            );

            foreach ($duplicateImages as $hash => $images) {
                sort($images);

                foreach (array_splice($images, 1) as $image) {
                    $output->writeln(
                        '| '
                        . str_pad($image, $longestString)
                        . ' | '
                        . str_pad($images[0], $longestString)
                        . ' |'
                    );
                }
            }

            $output->writeln(
                '+-'
                . str_pad('', $longestString, '-')
                . '-+-'
                . str_pad('', $longestString, '-')
                . '-+'
            );
        }
    }
}
