<?php
namespace towardstudio\passwordprotection\controllers;

use towardstudio\passwordprotection\PasswordProtection;

use Craft;
use craft\helpers\UrlHelper;
use craft\web\Controller;
use craft\helpers\StringHelper;
use craft\services\Volumes;
use craft\volumes\Local;

use yii\base\InvalidConfigException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class SettingsController extends Controller
{
	// Protected Properties
	// =========================================================================

	protected array|bool|int $allowAnonymous = [];

	// Public Methods
	// =========================================================================

	/**
	 * Plugin settings
	 *
	 * @param null|bool|Settings $settings
	 *
	 * @return Response The rendered result
	 * @throws \yii\web\ForbiddenHttpException
	 */
	public function actionPluginSettings(?PasswordProtection $settings = null): Response
    {
        // If settings don't exist, load them
		if ($settings === null) {
			$settings = PasswordProtection::$settings;
		}

        // echo '<pre>';
        //     var_dump($settings);
        // echo '</pre>';
        // die();

        // Get settings section
        $section = Craft::$app->request->getSegment(3);

		// Basic variables
		$variables["fullPageForm"] = true;
		$variables["selectedSubnavItem"] = "settings";
		$variables["settings"] = $settings;

		$variables["controllerHandle"] = "settings" . "/" . $section;

        // Get plugin variables
		$variables["cookieLife"] = $settings->cookieLife;
        $variables["maxLogin"] = $settings->maxLogin;
        $variables["maxLoginsPeriod"] = $settings->maxLoginsPeriod;
        $variables["includedSections"] = $settings->includedSections;

        // Get editable sections
        $variables['sections'] = Craft::$app->getEntries()->getEditableSections();

		return $this->renderTemplate(
			"passwordprotection/settings/" .
				($section ? (string) $section : ""),
			$variables
		);
	}

	/**
	 * Save System Settings
	 *
	 * @return Response The rendered result
	 * @throws \yii\web\ForbiddenHttpException
	 */
	public function actionSaveSystemSettings()
	{
		$this->requirePostRequest();
        $data = Craft::$app->getRequest()->getBodyParams();

        // Get plugin data
        $plugin = Craft::$app->getPlugins()->getPlugin('passwordprotection');
        $settings = $plugin->settings;

        $settings->cookieLife = $data['cookie'];
        $settings->maxLogin = $data['loginAttempts'];
        $settings->maxLoginsPeriod = $data['loginAttemptsPeriod'];

        if (
			!Craft::$app->getPlugins()->savePluginSettings($plugin, [$settings])
		) {
			Craft::$app
				->getSession()
				->setError(Craft::t("app", "Couldn't save plugin settings."));

			return $this->redirectToPostedUrl();
		}
	}

    /**
	 * Save Section Settings
	 *
	 * @return Response The rendered result
	 * @throws \yii\web\ForbiddenHttpException
	 */
	public function actionSaveSectionSettings()
	{
		$this->requirePostRequest();
        $data = Craft::$app->getRequest()->getBodyParams();

        // Remove unneeded items
        $includedSections = [];

        foreach ($data as $key => $element)
        {
            if ($element === "1")
            {
                array_push($includedSections, $key);
            }
        }

        // If there is excluded sections
        if (!empty($includedSections))
        {
            // Get plugin data
            $plugin = Craft::$app->getPlugins()->getPlugin('passwordprotection');
            $settings = $plugin->settings;

            $settings->includedSections = $includedSections;

            if (
			    !Craft::$app->getPlugins()->savePluginSettings($plugin, [$settings])
		    ) {
			    Craft::$app
				    ->getSession()
				    ->setError(Craft::t("app", "Couldn't save plugin settings."));

			    return $this->redirectToPostedUrl();
		    } else {
                return $this->redirectToPostedUrl();
            }

        }
	}

    /**
	 * Install Login Template
	 *
	 * @return Response The rendered result
	 */
	public function actionInstallTemplates()
	{
		$this->requirePostRequest();

		// Get Craft Template folder path
		$templatesFolder = Craft::getAlias('@templates') . '/protected-page';

		// Get Base Templates Path
		$passwordprotection = Craft::getAlias('@passwordprotection') . '/templates/protected-page';

		// Create new Structure
		try {
            PasswordProtection::getInstance()->copyTemplates->copyDirectory($passwordprotection, $templatesFolder);
		} catch (\Exception $e) {
			Craft::$app
				->getSession()
				->setNotice($e->getMessage());

			return $this->redirect("passwordprotection/settings/templates");
		}

		Craft::$app
		->getSession()
		->setNotice(
			Craft::t(
				"app",
				"Login Templates have been installed successfully"
			)
		);

		return $this->redirect("passwordprotection/settings/templates");
	}

}
