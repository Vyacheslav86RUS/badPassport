<?php

namespace app\modules\badPassport\models\import;

use app\modules\badPassport\models\import\interfaces\FmsInterface;
use yii\httpclient\Client;

class FmsDownload implements FmsInterface
{
    protected $client;
    public $rootDirFile;
    private $infoFile;

    /**
     * FmsDownload constructor.
     *
     * @param string $baseUrl url-ссылка на файла
     * @param string $realPathDir Реальный путь для работы с файлами
     */
    public function __construct($baseUrl, $realPathDir)
    {
        $this->client = new Client([
            'transport' => 'yii\httpclient\CurlTransport',
            'baseUrl' => $baseUrl,
        ]);
        $this->infoFile = $this->getFileInfo($baseUrl);
        $this->rootDirFile = $realPathDir . '/';
    }

    /**
     * Скачать файл
     *
     * @return string Возвращает путь до файла после скачивания
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\httpclient\Exception
     */
    public function download()
    {
        if ((false === file_exists($this->rootDirFile))
            && !mkdir($this->rootDirFile)
            && !is_dir($this->rootDirFile)
        ) {
            throw new \RuntimeException('Не удалось создать директорию ' . $this->rootDirFile);
        }
        $path = $this->rootDirFile . $this->infoFile['filename'] . '.' . $this->infoFile['extension'];
        $fp = fopen($path, 'w');

        $response = $this->client->createRequest()
            ->setMethod('GET')
            ->setOutputFile($fp)
            ->setOptions([CURLOPT_NOPROGRESS => false])
            ->send();

        fclose($fp);

        $fileSize = (int)$response->getHeaders()->get('content-length');

        if (false === file_exists($path) && filesize($path) !== $fileSize) {
            throw new \RuntimeException('Не удалось скачать файл ' . $path);
        }

        return $path;
    }

    /**
     * Получить файл
     *
     * @return string
     * @throws \yii\httpclient\Exception
     *
     * @throws \yii\base\InvalidConfigException
     */
    public function getFile()
    {
        return $this->download();
    }

    /**
     * Получить дату изменения файла
     *
     * @return string Возвращает дату последнего изменения файла в формате для поиска в БД
     * @throws \yii\httpclient\Exception
     *
     * @throws \yii\base\InvalidConfigException
     * @throws \Exception
     */
    public function getLastTimeModified()
    {
        $response = $this->client->createRequest()
            ->setMethod('HEAD')
            ->setFormat(Client::FORMAT_CURL)
            ->send();

        $date = new \DateTime($response->getHeaders()->get('last-modified'));

        return $date->format('d.m.Y H:i:s');
    }

    /**
     * Удалить файлы
     */
    public function deleteSource()
    {
        unlink($this->rootDirFile . $this->infoFile['filename'] . '.' . $this->infoFile['extension']);
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
        return pathinfo($path);
    }
}
