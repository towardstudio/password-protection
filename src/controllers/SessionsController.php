<?php

namespace towardstudio\passwordprotection\controllers;

use Craft;
use craft\web\Controller;
use craft\web\Response;
use craft\web\View;
use towardstudio\passwordprotection\Password;

class SessionsController extends Controller
{
    protected array|bool|int $allowAnonymous = self::ALLOW_ANONYMOUS_LIVE;

    public function actionCreate(): Response
    {
        Craft::$app->response->headers->set('X-Robots-Tag', 'none');
        return $this->renderTemplate('password-protected/login', [], View::TEMPLATE_MODE_SITE);
    }
}
