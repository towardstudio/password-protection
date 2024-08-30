<?php

namespace towardstudio\passwordprotection\services;

use Craft;
use craft\helpers\DateTimeHelper;
use craft\helpers\Db;
use DateTime;

use towardstudio\passwordprotection\PasswordProtection;
use towardstudio\passwordprotection\records\LoginAttempt;

use yii\base\Component;

class LoginAttemptService extends Component
{
    public function register(string $key)
    {
        $attempt = new LoginAttempt();
        $attempt->key = $key;
        $attempt->ipAddress = Craft::$app->request->userIP;
        $attempt->save();

        if (rand(1, 10) === 1) {
            $this->_cleanDatabase();
        }
    }

    public function tooMany(string $key): bool
    {
        $start = $this->_maxAttemptsPeriodStart();

        $count = LoginAttempt::find()
            ->where([
                'key' => $key,
                'ipAddress' => Craft::$app->request->userIP,
            ])
            ->andWhere(['>=', 'dateCreated', Db::prepareDateForDb($start)])
            ->count();

        return $count >= PasswordProtection::getInstance()->getSettings()->maxLogin;
    }

    private function _cleanDatabase()
    {
        $start = $this->_maxAttemptsPeriodStart();

        LoginAttempt::deleteAll(
            ['<', 'dateCreated', Db::prepareDateForDb($start)]
        );
    }

    private function _maxAttemptsPeriodStart(): DateTime
    {
        $period = DateTimeHelper::secondsToInterval(
            PasswordProtection::getInstance()->getSettings()->maxLoginsPeriod
        );

        return DateTimeHelper::currentUTCDateTime()->sub($period);
    }
}
