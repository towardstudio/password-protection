<?php

namespace towardstudio\passwordprotection\records;

use craft\db\ActiveRecord;

class LoginAttempt extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%site_login_attempts}}';
    }
}
