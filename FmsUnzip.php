<?php

namespace app\modules\badPassport\models\import;

use app\modules\badPassport\models\import\interfaces\FmsInterface;

/**
 * Class FmsUnzip
 *
 * @package app\modules\badPassport\models\import
 *
 * @property FmsDownload $fms;
 */
class FmsUnzip implements FmsInterface
{
    public $fms;
    private $infoFile;

    /**
     * FmsUnzip constructor.
     *
     * @param FmsInterface $fmsFile
     */
    public function __construct(FmsInterface $fmsFile)
    {
        $this->fms = $fmsFile;
    }

    /**
     * Извлечь файл из bz2
     *
     * @return string Метод должен возвращать путь до файла, после распаковки
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\httpclient\Exception
     */
    public function unzip()
    {
        $pathToFile = $this->fms->getFile();
        $this->infoFile = $this->getFileInfo($pathToFile);
        $pathUnzipFile = $this->infoFile['dirname'] . '/' . $this->infoFile['filename'];

        exec('bunzip2 --force --keep ' . $pathToFile);

        if (false === file_exists($pathUnzipFile) || filesize($pathUnzipFile) <= 0) {
            throw new \RuntimeException('Не удалось распаковать файл: ' . $this->infoFile['basename']);
        }

        return $pathUnzipFile;
    }

    /**
     * Получить файл
     *
     * @return string путь до файла
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\httpclient\Exception
     */
    public function getFile()
    {
        return $this->unzip();
    }

    /**
     * Получить дату изменения файла
     *
     * @return mixed
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\httpclient\Exception
     */
    public function getLastTimeModified()
    {
        return $this->fms->getLastTimeModified();
    }

    /**
     * Удалить файлы
     */
    public function deleteSource()
    {
        unlink($this->fms->rootDirFile . $this->infoFile['filename']);
        $this->fms->deleteSource();
    }

    /**
     * Получить информацию о файле
     *
     * @param string $path путь до файла
     *
     * @return mixed
     */
    public function getFileInfo($path)
    {
        return $this->fms->getFileInfo($path);
    }
}
