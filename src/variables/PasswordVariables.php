<?php

namespace towardstudio\passwordprotection\variables;

use towardstudio\passwordprotection\PasswordProtection;

class PasswordVariables
{
    public function protect(?string $password = null, ?string $key = null)
    {
        PasswordProtection::getInstance()->passwordService->protect($password, $key);
    }

    public function stillProtected($entryId)
    {
        $password = PasswordProtection::getInstance()->passwordEntry->get($entryId);

        $protected = !empty($password) && $password !== '';

        return $protected;
    }
}
