<?php
namespace NathanDay\CatalogImagesClean\Command;

use Magento\Catalog\Model\ResourceModel\Product\Gallery;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputOption;
use Magento\Framework\Console\Cli;

/**
 * Class CleanCommand
 *
 * @package NathanDay\CatalogImagesClean\Command
 */
class CleanCommand extends AbstractCommand
{
    /** @var string */
    const INPUT_KEY_DRYRUN = 'dry-run';

    /**
     * Configure Function
     *
     * Set Command Name and Options
     */
    protected function configure()
    {
        $this->setName('catalog:images:clean')
            ->setDescription(
                'Clean Catalog Images, Delete Unused Images in the Filesystem and/or Remove records in the Database '
                . 'for Missing Images'
            )->addOption(
                self::INPUT_KEY_UNUSED,
                'u',
                InputOption::VALUE_NONE,
                'Delete unused product images'
            )->addOption(
                self::INPUT_KEY_MISSING,
                'm',
                InputOption::VALUE_NONE,
                'Remove missing product image Records'
            )->addOption(
                self::INPUT_KEY_DUPLICATE,
                't',
                InputOption::VALUE_NONE,
                'Remove duplicate product images and update database Records'
            )->addOption(
                self::INPUT_KEY_DRYRUN,
                'd',
                InputOption::VALUE_NONE,
                'Dry Run, don\'t make any changes'
            );

        parent::configure();
    }

    /**
     * Execute Function
     *
     * Entry point to catalog:images:clean command
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
        $output->writeln('<fg=green;options=bold>Catalog Product Image Cleaning</>');
        $output->writeln('<fg=green;options=bold>======================================</>');

        if ($input->getOption(self::INPUT_KEY_MISSING)
            || $input->getOption(self::INPUT_KEY_UNUSED)
            || $input->getOption(self::INPUT_KEY_DUPLICATE)) {
            if ($input->getOption(self::INPUT_KEY_MISSING)) {
                $this->executeMissingImages($input, $output);
            }

            if ($input->getOption(self::INPUT_KEY_UNUSED)) {
                $this->executeUnusedImages($input, $output);
            }

            if ($input->getOption(self::INPUT_KEY_DUPLICATE)) {
                $this->executeDuplicateImages($input, $output);
            }
        } else {
            $this->executeMissingImages($input, $output);
            $this->executeUnusedImages($input, $output);
            $this->executeDuplicateImages($input, $output);
        }

        return Cli::RETURN_SUCCESS;
    }

    /**
     * Execute Missing Images Cleanup Logic
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws \Magento\Framework\Exception\FileSystemException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function executeMissingImages(InputInterface $input, OutputInterface $output)
    {
        $output->writeln(PHP_EOL . '<info>Missing Product Images</info>' . PHP_EOL);

        $dryRun = $input->getOption(self::INPUT_KEY_DRYRUN);

        $missingImages = $this->getMissingProductImages();
        $missingImageCount = count($missingImages);

        if ($dryRun) {
            $output->writeln($missingImageCount . ' Gallery records to be removed');
            $this->verboseMissingImages($output, $missingImages);
        } else {
            if ($missingImageCount < 0) {
                $output->writeln('<comment>You are about to remove database Records.</comment>');
                $output->writeln('<comment>It is recommended to take a database backup before proceeding</comment>');
                $output->writeln('');

                /** @var QuestionHelper $helper */
                $helper = $this->getHelper('question');
                $question = new ConfirmationQuestion(
                    '<question>Do you want to continue? [y/N]:</question> ',
                    false
                );

                if (!$helper->ask($input, $output, $question) && $input->isInteractive()) {
                    $output->writeln('<error>Not Proceeding</error>');
                    $output->writeln(PHP_EOL . '<fg=green;options=bold>======================================</>');

                    return Cli::RETURN_FAILURE;
                }

                $output->writeln('Removing Gallery Records');
                $this->verboseMissingImages($output, $missingImages);

                $this->gallery->deleteGallery(array_keys($missingImages));
            } else {
                $output->writeln('There are no missing image records to remove');
            }
        }

        $output->writeln(PHP_EOL . '<fg=green;options=bold>======================================</>');
    }

    /**
     * Execute Unused Images Cleanup Logic
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @throws \Magento\Framework\Exception\FileSystemException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function executeUnusedImages(InputInterface $input, OutputInterface $output)
    {
        $output->writeln(PHP_EOL . '<info>Unused Product Images</info>' . PHP_EOL);

        $dryRun = $input->getOption(self::INPUT_KEY_DRYRUN);

        $unusedImages = $this->getUnusedProductImages();
        $unusedImageCount = $this->getUnusedProductImageCount();

        if ($dryRun) {
            $output->writeln($unusedImageCount . ' Catalog product image files to be deleted');
            $this->verboseUnusedImages($output, $unusedImages);
        } else {
            if ($unusedImageCount > 0) {
                $output->writeln('');
                $output->writeln('<comment>You are about to remove catalog image files.</comment>');
                $output->writeln('<comment>It is recommended to take a media backup before proceeding</comment>');
                $output->writeln('');

                /** @var QuestionHelper $helper */
                $helper = $this->getHelper('question');
                $question = new ConfirmationQuestion(
                    '<question>Do you want to continue? [y/N]:</question> ',
                    false
                );

                if (!$helper->ask($input, $output, $question) && $input->isInteractive()) {
                    $output->writeln('<error>Not Proceeding</error>');
                    $output->writeln(PHP_EOL . '<fg=green;options=bold>======================================</>');

                    return Cli::RETURN_FAILURE;
                }

                $output->writeln('Deleting catalog images');
                $this->verboseUnusedImages($output, $unusedImages);

                $progress = new ProgressBar($output, $this->getUnusedProductImageCount());
                $progress->setFormat('<comment>%message%</comment> %current%/%max% [%bar%] %percent:3s%% %elapsed%');

                foreach ($this->getUnusedProductImages() as $image) {
                    $progress->setMessage($image . '');
                    $this->fileDriver->deleteFile($this->getFullImagePath($image));
                    $progress->advance();
                }
            } else {
                $output->writeln('There are no unused images to delete');
            }
        }

        $output->writeln(PHP_EOL . '<fg=green;options=bold>======================================</>');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @throws \Magento\Framework\Exception\FileSystemException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function executeDuplicateImages(InputInterface $input, OutputInterface $output)
    {
        $output->writeln(PHP_EOL . '<info>Duplicate Product Images</info>' . PHP_EOL);

        $dryRun = $input->getOption(self::INPUT_KEY_DRYRUN);
        $duplicateImages = $this->getDuplicateProductImages();
        $duplicateImageCount = $this->getDuplicateProductImageCount();
        $databaseRecords = $this->getDatabaseProductImages();
        $databaseRecordCount = 0;

        foreach ($duplicateImages as $hash => $images) {
            sort($images);
            $newImagesArray = [];
            $newImagesArray['keeper'] = $images[0];
            unset($images[0]);

            foreach ($images as $image) {
                $newImagesArray[$image] = array_keys($databaseRecords, $image);
                $databaseRecordCount += count($newImagesArray[$image]);
            }

            $duplicateImages[$hash] = $newImagesArray;
        }

        if ($dryRun) {
            $output->writeln($duplicateImageCount . ' Catalog product image files to be deleted');
            $output->writeln('and ' . $databaseRecordCount . ' Database Records to be updated');
            $this->verboseDuplicateImages($output, $duplicateImages);
            $output->writeln(PHP_EOL . '<fg=green;options=bold>======================================</>');

            return true;
        }

        if ($duplicateImageCount > 0) {
            $output->writeln('');
            $output->writeln(
                '<comment>You are about to remove catalog image files ad update database records.</comment>'
            );
            $output->writeln(
                '<comment>It is recommended to take a media and database backup before proceeding</comment>'
            );
            $output->writeln('');

            /** @var QuestionHelper $helper */
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion(
                '<question>Do you want to continue? [y/N]:</question> ',
                false
            );

            if (!$helper->ask($input, $output, $question) && $input->isInteractive()) {
                $output->writeln('<error>Not Proceeding</error>');
                $output->writeln(PHP_EOL . '<fg=green;options=bold>======================================</>');

                return Cli::RETURN_FAILURE;
            }

            $output->writeln('Deleting catalog images');
            $this->verboseDuplicateImages($output, $duplicateImages);

            $progress = new ProgressBar($output, $databaseRecordCount + $duplicateImageCount);
            $progress->setFormat('<comment>%message%</comment> %current%/%max% [%bar%] %percent:3s%% %elapsed%');

            foreach ($duplicateImages as $hash => $data) {
                foreach ($data as $key => $dbRecords) {
                    if ($key == 'keeper') continue;

                    $progress->setMessage('Deleting: ' . $key);
                    $this->fileDriver->deleteFile($this->getFullImagePath($key));
                    $progress->advance();

                    foreach ($dbRecords as $dbRecord) {
                        $progress->setMessage('Update DB Record: ' . $dbRecord);
                        $this->gallery->saveDataRow(
                            Gallery::GALLERY_TABLE,
                            [
                                'value_id' => $dbRecord,
                                'value' => $data['keeper'],
                            ]
                        );
                        $progress->advance();
                    }
                }
            }
        } else {
            $output->writeln('There are no duplicate images to delete or update');
        }

        $output->writeln(PHP_EOL . '<fg=green;options=bold>======================================</>');
    }

    /**
     * Verbose Missing Images Message
     *
     * @param OutputInterface $output
     * @param array $missingImages
     */
    protected function verboseMissingImages(OutputInterface $output, array $missingImages)
    {
        if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE && count($missingImages) > 0) {
            $longestString = max(array_map('strlen', $missingImages));

            $output->writeln('+----------+-' . str_pad('', $longestString, '-') . '-+');
            $output->writeln('| value_id | ' . str_pad('value', $longestString) . ' |');
            $output->writeln('+----------+-' . str_pad('', $longestString, '-') . '-+');

            foreach ($missingImages as $key => $image) {
                $output->writeln('| ' . str_pad($key, 8) . ' | ' . str_pad($image, $longestString) . ' |');
            }

            $output->writeln('+----------+-' . str_pad('', $longestString, '-') . '-+');
        }
    }

    /**
     * Verbose Unused Images Message
     *
     * @param OutputInterface $output
     * @param array $unusedImages
     */
    protected function verboseUnusedImages(OutputInterface $output, array $unusedImages)
    {
        if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE && count($unusedImages) > 0) {
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

    protected function verboseDuplicateImages(OutputInterface $output, array $duplicateImages)
    {
        if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE
            && $this->getDuplicateProductImageCount() > 0) {
            $imagePaths = [];
            array_walk_recursive($duplicateImages, function ($a) use (&$imagePaths) { $imagePaths[] = $a; });
            $headers = ['Filename', 'Duplicate of', 'Database Records'];
            $longestString = max(array_map('strlen', array_merge($imagePaths, $headers)));

            $divider = $this->getOutputDivider($headers, $longestString);
            $titleLine = $this->getOutputTitles($headers, $longestString);

            $output->writeln($divider);
            $output->writeln($titleLine);
            $output->writeln($divider);

            foreach ($duplicateImages as $hash => $data) {
                foreach ($data as $key => $dbRecords) {
                    if ($key == 'keeper') continue;

                    $output->writeln(
                        '| '
                        . str_pad($key, $longestString)
                        . ' | '
                        . str_pad($data['keeper'], $longestString)
                        . ' | '
                        . str_pad(count($dbRecords), $longestString)
                        . ' |'
                    );
                }
            }

            $output->writeln($divider);
        }
    }
}
