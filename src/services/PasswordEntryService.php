<?php

namespace towardstudio\passwordprotection\services;

use Craft;
use craft\helpers\DateTimeHelper;
use craft\helpers\Db;
use DateTime;

use towardstudio\passwordprotection\records\PasswordRecord;
use towardstudio\passwordprotection\Password;

use yii\base\Component;

class PasswordEntryService extends Component
{
    public function register(?int $id, ?string $password)
    {
        if (!empty($id))
        {
            $transaction = Craft::$app->getDb()->beginTransaction();
            $record = PasswordRecord::find()
        	    ->andWhere('elementId=:elementId')
        	    ->addParams(['elementId' => (int)$id])
        	    ->one();

            if (!$record && $password) {
                $password = base64_encode(Craft::$app->security->encryptByKey($password));
                $record = new PasswordRecord();
                $record->setAttribute('elementId', $id);
                $record->setAttribute('password', $password);
            } else {
                if (!empty($password)) {
                    $password = base64_encode(Craft::$app->security->encryptByKey($password));
                    $record->setAttribute('password', $password);
                } else if($record) {
                    $record->setAttribute('password', '');
                }
            }

            if ($record) {
                if ($password)
                {
                    $record->save();
                } else {
                    $record->delete();
                }
            };

		    $transaction->commit();

        }
    }

    public function get(?int $id) : string
    {
        if (empty($id))
        {
            return '';
        }

        $record = PasswordRecord::find()
        	->andWhere('elementId=:elementId')
        	->addParams(['elementId' => (int)$id])
        	->one();

        if ($record) {
            $password = $record->getAttribute('password');
            return Craft::$app->security->decryptByKey(base64_decode($password));
        } else {
            return '';
        }
    }

}
