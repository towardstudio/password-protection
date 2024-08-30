<?php

namespace towardstudio\passwordprotection\records;

use craft\db\ActiveRecord;

class PasswordRecord extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%site_entry_passwords}}';
    }
}
