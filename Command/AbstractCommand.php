<?php
namespace NathanDay\CatalogImagesClean\Command;

use Symfony\Component\Console\Command\Command;
use Magento\Framework\Filesystem;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\Catalog\Model\Product\Media\ConfigInterface as MediaConfig;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Query\Generator;
use Magento\Framework\DB\Select;
use Magento\Framework\App\ResourceConnection;
use Magento\Catalog\Model\ResourceModel\Product\Image as ProductImage;
use Magento\Catalog\Model\ResourceModel\Product\Gallery;
use Magento\Framework\Filesystem\Driver\File;

/**
 * Class AbstractCommand
 *
 * @package NathanDay\CatalogImagesClean\Command
 */
class AbstractCommand extends Command
{
    /** Input Keys */
    const INPUT_KEY_MISSING   = 'missing';
    const INPUT_KEY_UNUSED    = 'unused';
    const INPUT_KEY_DUPLICATE = 'duplicate';

    /** @var Filesystem */
    protected $filesystem;

    /** @var WriteInterface */
    protected $mediaDirectory;

    /** @var array */
    protected $databaseImages;
    protected $missingImages;
    protected $physicalImages;
    protected $unusedImages;
    protected $duplicateImages;

    /** @var MediaConfig */
    protected $imageConfig;

    /** @var Generator */
    protected $batchQueryGenerator;

    /** @var ResourceConnection */
    protected $resourceConnection;

    /** @var AdapterInterface */
    protected $connection;

    /** @var int */
    protected $batchSize;

    /** @var ProductImage */
    protected $productImage;

    /** @var File */
    protected $fileDriver;

    /** @var Gallery */
    protected $gallery;

    public function __construct(
        Generator $generator,
        ResourceConnection $resourceConnection,
        Filesystem $filesystem,
        MediaConfig $imageConfig,
        ProductImage $productImage,
        File $fileDriver,
        Gallery $gallery,
        $batchSize = 100
    ) {
        parent::__construct();

        $this->filesystem = $filesystem;
        $this->batchQueryGenerator = $generator;
        $this->resourceConnection = $resourceConnection;
        $this->connection = $this->resourceConnection->getConnection();
        $this->batchSize = $batchSize;
        $this->mediaDirectory = $filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        $this->imageConfig = $imageConfig;
        $this->productImage = $productImage;
        $this->fileDriver = $fileDriver;
        $this->gallery = $gallery;
    }

    /**
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function getDatabaseProductImages(array $filter = [])
    {
        if (!isset($this->databaseImages)) {
            $this->databaseImages = [];
            foreach ($this->getDatabaseProductImagesGenerator($filter) as $image) {
                $this->databaseImages[$image['id']] = $image['filepath'];
            }
        }

        return $this->databaseImages;
    }

    /**
     * @return int
     */
    protected function getDatabaseProductImageCount()
    {
        return $this->productImage->getCountAllProductImages();
    }

    /**
     * @return mixed
     * @throws \Magento\Framework\Exception\FileSystemException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function getMissingProductImages()
    {
        if (!isset($this->missingImages)) {
            $this->missingImages = [];
            foreach ($this->getDatabaseProductImages() as $key => $imagePath) {
                if (!$this->doesFileExist($imagePath)) {
                    $this->missingImages[$key] = $imagePath;
                }
            }
        }

        return $this->missingImages;
    }

    /**
     * @return int
     * @throws \Magento\Framework\Exception\FileSystemException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getMissingProductImageCount()
    {
        return count(array_unique($this->getMissingProductImages()));
    }

    /**
     * @return mixed
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    protected function getPhysicalProductImages()
    {
        $mediaDirectoryPath = $this->mediaDirectory->getAbsolutePath($this->imageConfig->getBaseMediaPath());
        if (!isset($this->physicalImages)) {
            $this->physicalImages = [];
            $physicalImages = $this->fileDriver->readDirectoryRecursively($mediaDirectoryPath);

            foreach ($physicalImages as $imagePath) {
                if ($this->fileDriver->isFile($imagePath)
                    && strpos($imagePath, DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR) === false) {
                    $this->physicalImages[substr($imagePath, strlen($mediaDirectoryPath))] = [
                        'hash' => md5_file($imagePath),
                        'filesize' => filesize($imagePath),
                    ];
                }
            }
        }

        return $this->physicalImages;
    }

    /**
     * @return int
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    protected function getPhysicalProductImageCount()
    {
        return count(array_keys($this->getPhysicalProductImages()));
    }

    /**
     * @return array
     * @throws \Magento\Framework\Exception\FileSystemException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function getUnusedProductImages()
    {
        if (!isset($this->unusedImages)) {
            $this->unusedImages = [];
            $this->unusedImages = array_diff(array_keys($this->getPhysicalProductImages()), $this->getDatabaseProductImages());
        }

        return $this->unusedImages;
    }

    /**
     * @return int
     * @throws \Magento\Framework\Exception\FileSystemException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getUnusedProductImageCount()
    {
        return count(array_unique($this->getUnusedProductImages()));
    }

    /**
     * Get duplicate physical images
     *
     * @return array
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    public function getDuplicateProductImages()
    {
        if (!isset($this->duplicateImages)) {
            $this->duplicateImages = [];
            $physicalImages = array_map(
                function($physicalImage) { return $physicalImage['hash']; },
                $this->getPhysicalProductImages()
            );
            foreach (array_count_values($physicalImages) as $hash => $count){
                if ($count > 1) {
                    $this->duplicateImages[$hash] = array_keys($physicalImages, $hash);
                }
            }
        }

        return $this->duplicateImages;
    }

    /**
     * Get count of duplicate product images
     *
     * @return int
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    public function getDuplicateProductImageCount()
    {
        $duplicateImages = $this->getDuplicateProductImages();
        return (int)array_sum(array_map("count", $duplicateImages)) - count($duplicateImages);
    }

    /**
     * @param array $where
     * @return \Generator
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function getDatabaseProductImagesGenerator(array $where = []): \Generator
    {
        $select = $this->getallImagesSelect();

        if (!empty($where)) {
            if (!isset($where['value']))
                $where['value'] = null;

            if (!isset($where['type']))
                $where['type'] = null;

            $select = $select->where($where['cond'], $where['value'], $where['type']);

            var_dump($select->__toString());
        }

        $batchSelectIterator = $this->batchQueryGenerator->generate(
            'value_id',
            $select,
            $this->batchSize,
            \Magento\Framework\DB\Query\BatchIteratorInterface::NON_UNIQUE_FIELD_ITERATOR
        );

        foreach ($batchSelectIterator as $select) {
            foreach ($this->connection->fetchAll($select) as $key => $value) {
                yield $key => $value;
            }
        }
    }



    /**
     * Return Select to fetch all products images
     *
     * @return Select
     */
    protected function getAllImagesSelect(): Select
    {
        return $this->connection->select()
            ->from(
                ['images' => $this->resourceConnection->getTableName(Gallery::GALLERY_TABLE)],
                ['value_id as id', 'value as filepath']
            );
    }

    /**
     * @param $filepath
     * @return bool
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    protected function doesFileExist($filepath)
    {
        return $this->fileDriver->isExists($this->getFullImagePath($filepath));
    }

    /**
     * @param $imagePath
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    protected function deleteFile($imagePath)
    {
        $this->fileDriver->deleteFile($this->getFullImagePath($imagePath));
    }

    /**
     * @param array|integer $id
     */
    public function deleteGallery($id)
    {
        $this->gallery->deleteGallery($id);
    }

    /**
     * @param $filename
     * @return string
     */
    protected function getFullImagePath($filename)
    {
        return $this->mediaDirectory->getAbsolutePath(
            $this->imageConfig->getMediaPath($filename)
        );
    }

    protected function getOutputDivider(array $headers, int $padding)
    {
        $divider = '+-';

        foreach ($headers as $key => $header) {
            $divider .= str_pad('', $padding, '-');
            if ($key === array_key_last($headers)) {
                $divider .= '-+';
            } else {
                $divider .= '-+-';
            }
        }

        return $divider;
    }

    protected function getOutputTitles(array $headers, int $padding)
    {
        $titleLine = '| ';

        foreach ($headers as $key => $header) {
            $titleLine .= str_pad($header, $padding, ' ');
            if ($key === array_key_last($headers)) {
                $titleLine .= ' |';
            } else {
                $titleLine .= ' | ';
            }
        }

        return $titleLine;
    }
}
