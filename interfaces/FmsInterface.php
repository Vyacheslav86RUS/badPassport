<?php

namespace app\modules\badPassport\models\import\interfaces;

interface FmsInterface
{
    public function getFile();

    public function getLastTimeModified();

    public function deleteSource();

    public function getFileInfo($path);
}
