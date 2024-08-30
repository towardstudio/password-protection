<?php

namespace towardstudio\passwordprotection\services;

use Craft;
use craft\helpers\UrlHelper;

use towardstudio\passwordprotection\PasswordProtection;

use yii\base\Component;
use yii\web\Cookie;

use InvalidArgumentException;

class PasswordService extends Component
{
    const COOKIE_NAME = 'CraftEntryPassword';
    const MAX_KEYS = 10;

    public function protect(int $entryId, ?string $key = null)
    {
        if (empty($entryId))
        {
            return;
        }

        if ($key === null) {
            throw new InvalidArgumentException("A key describing the protected page(s) must be provided as the second argument to the protect method.\nExample: {% do craft.templateGuard.protect('Pa\$\$w0rd', 'secret-page') %}");
        }

        $passwordAttempt = Craft::$app->request->getParam('password');

        // Get Password for Entry
        $password = PasswordProtection::getInstance()->passwordEntry->get($entryId);

        // Encrypt Key
        $key = $this->_generateKey($key, $password);

        if (empty($password) || $this->_loggedIn($key)) {
            return;
        }

        if ($passwordAttempt) {
            return $this->_login($entryId, $key, $password, $passwordAttempt);
        }

        $this->_redirectToLoginPage($entryId);
    }

    private function _generateKey(?string $key, ?string $password = null): string
    {
        // The length of the key will be 40 chars (sha1). We'll use this
        // predictable length to check how many keys we can store in
        // one cookie.
        return sha1($key . "-" . $password);
    }

    private function _loggedIn(string $key): bool
    {
        return in_array($key, $this->_getKeysFromCookie(), true);
    }

    private function _redirectToLoginPage(int $entryId)
    {
        $params = [
            'ref' => $this->_protectedUrl(),
            'refId' => $entryId,
        ];

        $route = PasswordProtection::getInstance()->getRoute();

        $loginUrl = UrlHelper::siteUrl($route, $params);

        Craft::$app->response->redirect($loginUrl);
        Craft::$app->end();
    }

    private function _login(
        int $entryId,
        string $key,
        string $password,
        string $passwordAttempt
    ) {
        $loginAttempts = PasswordProtection::getInstance()->loginAttempt;

        if (!Craft::$app->request->validateCsrfToken()) {
            $this->_setError('Invalid CSRF token.');

            return $this->_redirectToLoginPage($entryId);
        }

        if ($loginAttempts->tooMany($key)) {
            $this->_setError('Too many attempts, try again later.');

            return $this->_redirectToLoginPage($entryId);
        }

        if ($password !== $passwordAttempt) {
            $loginAttempts->register($key);

            $this->_setError('Invalid password.');

            return $this->_redirectToLoginPage($entryId);
        }

        $this->_addToCookie($key);
    }

    private function _protectedUrl()
    {
        return Craft::$app->request->absoluteUrl;
    }

    private function _setError(string $error)
    {
        Craft::$app->session->setFlash('error', Craft::t('passwordprotection', $error));
    }

    private function _addToCookie(string $key)
    {
        $keys = array_slice([
            ...$this->_getKeysFromCookie(), $key
        ], self::MAX_KEYS * -1);

        $this->_setKeysOnCookie($keys);
    }

    private function _removeAllFromCookie()
    {
        $this->_setKeysOnCookie([]);
    }

    private function _removeFromCookie(string $keyToRemove)
    {
        $keys = array_filter(
            $this->_getKeysFromCookie(),
            fn ($key) => $key !== $keyToRemove,
        );

        $this->_setKeysOnCookie($keys);
    }

    private function _getKeysFromCookie(): array
    {
        $cookies = Craft::$app->request->cookies;
        $cookie = $cookies->getValue(self::COOKIE_NAME);

        return json_decode($cookie) ?: [];
    }

    private function _setKeysOnCookie(array $keys)
    {
        Craft::$app->response->cookies->add(new Cookie([
            'name' => self::COOKIE_NAME,
            'value' => json_encode($keys),
            'expire' => time() + PasswordProtection::getInstance()->getSettings()->cookieLife,
        ]));
    }
}
