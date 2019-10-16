<?php

namespace app\modules\badPassport\models\import;

use app\modules\badPassport\models\import\interfaces\FmsInterface;
use app\modules\badPassport\models\logical\TablesListLogical;
use app\modules\badPassport\models\logical\UpdateDdLogLogical;
use newcontact\oraclepack\Connection;
use yii\db\Expression;

class FmsImport
{
    public $fms;
    private $rootPathModule;
    private $connect;
    private $rootDirFile;

    /**
     * FmsImport constructor.
     *
     * @param FmsInterface $fmsFile
     * @param Connection $connection
     */
    public function __construct(FmsInterface $fmsFile, Connection $connection)
    {
        $this->fms = $fmsFile;
        $this->connect = $connection;
        $this->rootPathModule = \Yii::getAlias('@root_modules');
    }

    /**
     * Проверка имеются ли IS_ACTUAL = UPDATED_IN_PROGRESS
     *
     * @return bool Возвращает true если была найдена запись с IS_ACTUAL = UPDATED_IN_PROGRESS, иначе false
     */
    public function hasActiveImportProcess()
    {
        return UpdateDdLogLogical::find()
            ->where(['IS_ACTUAL' => UpdateDdLogLogical::UPDATED_IN_PROGRESS])
            ->exists();
    }

    /**
     * Метод снимает для всех записей IS_ACTUAL = UPDATED_IN_PROGRESS, что начали работу больше часу назад
     * Метод должен быть написан на чистом sql с использованием ActiveRecord::updateAll
     *
     * @return int Возвращает кол-во измененных записей
     * @throws \Exception
     */
    public function unlockOldActiveImportProcess()
    {
        $condition = [
            'and',
            [
                '<',
                new Expression("NEWCONTACT_UPDATE + INTERVAL '1' HOUR"),
                new Expression('systimestamp')
            ],
            ['in', 'IS_ACTUAL', [UpdateDdLogLogical::UPDATED_IN_PROGRESS]],
        ];

        return UpdateDdLogLogical::updateAll(
            ['IS_ACTUAL' => UpdateDdLogLogical::UPDATED_NOT_PRODUCED],
            $condition
        );
    }

    /**
     * Проверка даты изменения файла
     *
     * @return bool Возвращает true, если файл изменился и у нас записи нет, иначе false
     */
    public function isFileModified()
    {
        $result = UpdateDdLogLogical::find()
            ->where(['FMS_UPDATE' => $this->fms->getLastTimeModified()])
            ->andWhere(['IS_ACTUAL' => UpdateDdLogLogical::UPDATED])
            ->exists();

        return !$result;
    }

    /**
     * Импорт данных в бд
     *
     * @throws \RuntimeException Когда ошибки верхнего уровня (не скачался файл)
     * @throws \yii\db\Exception Когда не удалось сохранить модели
     * @throws \Throwable
     */
    public function import()
    {
        /** @var TablesListLogical $oldUpdatedTable */
        $oldUpdatedTable = $this->getOldTableUpdate();

        /** @var UpdateDdLogLogical $logInstance */
        $logInstance = $oldUpdatedTable->logInstance($this->fms->getLastTimeModified());

        if (false === $this->sqlldr($this->fms->getFile(), $oldUpdatedTable->TABLE_NAME)) {
            throw new \RuntimeException('Не удалось импортировать данные. Посмотрите log файл в вашей директории');
        }

        $logInstance->recalculateData();

        $oldUpdatedTable->updateTime();

        $this->deleteSource();

        return true;
    }

    /**
     * Получить модель TablesListLogical
     *
     * @return TablesListLogical|array|\yii\db\ActiveRecord
     *
     * @throws \RuntimeException Выбрасывает когда нет таблиц
     */
    public function getOldTableUpdate()
    {
        $model = TablesListLogical::find()
            ->orderBy(['LAST_UPDATE' => SORT_ASC])
            ->one();

        if (null === $model) {
            throw new \RuntimeException('Заполните таблицу ' . TablesListLogical::tableName());
        }

        return $model;
    }

    /**
     * Процесс вставки данных из файла в таблицу
     *
     * @param string $pathCsv путь до файла csv
     * @param string $tableName
     *
     * @return bool завершение процесса
     */
    protected function sqlldr($pathCsv, $tableName)
    {
        $infoFile = $this->fms->getFileInfo($pathCsv);
        $this->rootDirFile = $infoFile['dirname'];
        $nameDb = explode('=', $this->connect->dsn);

        exec("sed -e 's/{table}/" .
            $tableName .
            "/g' -e 's/{file}/" .
            str_replace('/', '\/', $pathCsv) .
            "/g' $this->rootPathModule/config/list_of_expired_passports.ctl > {$infoFile['dirname']}/list_of_expired_passports.ctl");
        exec('sqlldr ' .
            $this->connect->username .
            '/' .
            $this->connect->password .
            '@' .
            $nameDb[1] .
            ' control=' .
            $infoFile['dirname'] .
            '/list_of_expired_passports.ctl log=' .
            $infoFile['dirname'] .
            '/list_of_expired_passports.log', $output, $response);

        return 0 === (int)$response;
    }

    /**
     * Удаление не нужных файлов
     *
     * @return bool
     */
    protected function deleteSource()
    {
        $this->fms->deleteSource();
        $path = $this->rootDirFile . '/*';
        exec('rm -f ' . $path, $output, $response);

        return 0 === (int)$response;
    }
}
