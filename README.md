# Импорт просроченных паспортов

Данный класс можно встроить в модуль yii2

## Пример использования

$dir - путь, куда будет скачиваться файл
$url - адрес, где расположен файл
$db - объект подключения к БД

        $import = new FmsImport(
            new FmsUnzip(new FmsDownload($url, $dir)),
            $db
        );

        $importProcess = $import->unlockOldActiveImportProcess();

        if ($importProcess) {
            echo $importProcess . ' процессов завершили работу' . PHP_EOL;
        }

        echo 'Проверяем изменения файла' . PHP_EOL;
        if (!$import->isFileModified()) {
            echo 'Файл не изменялся' . PHP_EOL;

            return 1;
        }

        echo 'Проверяем, запущенные процессы' . PHP_EOL;

        if ($import->hasActiveImportProcess()) {
            echo 'Происходит импорт данных, попробуйте позжe' . PHP_EOL;

            return 1;
        }

        if ($import->import()) {
            echo 'Данные импортированы' . PHP_EOL;

            return 1;
        }

        echo 'Произошла ошибка импорта' . PHP_EOL;

        return 0;

