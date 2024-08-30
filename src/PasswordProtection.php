<?php
namespace towardstudio\passwordprotection;

// Craft
use Craft;
use craft\base\Element;
use craft\base\Model;
use craft\base\Plugin;
use craft\elements\Entry;
use craft\events\DefineHtmlEvent;
use craft\events\ModelEvent;
use craft\events\PluginEvent;
use craft\events\RegisterTemplateRootsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\helpers\UrlHelper;
use craft\services\Plugins;
use craft\services\UserPermissions;
use craft\i18n\PhpMessageSource;
use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;
use craft\web\View;

// Blitz
use putyourlightson\blitz\Blitz;

// Plugin
use towardstudio\passwordprotection\models\Settings;
use towardstudio\passwordprotection\variables\PasswordVariables;
use towardstudio\passwordprotection\services\CopyTemplates;
use towardstudio\passwordprotection\services\PasswordService;
use towardstudio\passwordprotection\services\LoginAttemptService;
use towardstudio\passwordprotection\services\PasswordEntryService;
use towardstudio\passwordprotection\twigextensions\LoginExtensions;

// Yii
use yii\base\Event;
use Exception;

/**
 * @author    Toward Studio
 * @package   EntryTemplates
 * @since     1.0.0
 *
 */
class PasswordProtection extends Plugin
{
    // Public Methods
    // =========================================================================

    /**
     * @var Listings
     */
    public static $instance;
    public bool $hasCpSettings = true;
    public static ?Settings $settings;

    /**
     * Initializes the module.
     */
    public function init()
    {
        parent::init();
        self::$instance = $this;
        self::$settings = $this->getSettings();

        // Create Custom Alias
		Craft::setAlias('@passwordprotection', __DIR__);

        // Register services
        $this->setComponents([
            'copyTemplates'     => CopyTemplates::class,
            'passwordService'   => PasswordService::class,
            'loginAttempt'      => LoginAttemptService::class,
            'passwordEntry'     => PasswordEntryService::class,
        ]);

        // Register Craft Events
        $this->_afterInstall();
        $this->_registerTwigExtensions();
        $this->_registerVariables();
        $this->_registerRoutes();
        $this->_registerTemplates();

        // Register Permissions
        $this->_registerPermissions();

        // Register Plugin Events
        $this->_registerSidebar();
        $this->_savePassword();

        // Register Translation Category
        Craft::$app->i18n->translations['passwordprotection'] = [
            'class' => PhpMessageSource::class,
            'sourceLanguage' => 'en',
            'basePath' => __DIR__ . '/translations',
            'allowOverrides' => true,
        ];
    }

    /**
	 * @title: Get Route
	 * @description: Get the Login Route
	 **/
    public function getRoute(): string
    {
        return "protected-page/login";
    }

    // Private Methods
    // =========================================================================


    /**
     * After Install Event
     */
    private function _afterInstall()
    {
        // Handler: EVENT_AFTER_INSTALL_PLUGIN
        Event::on(
            Plugins::class,
            Plugins::EVENT_AFTER_INSTALL_PLUGIN,
            function(PluginEvent $event) {
                if ($event->plugin === $this) {
                    $request = Craft::$app->getRequest();
                    if ($request->isCpRequest) {
                        Craft::$app->getResponse()->redirect(UrlHelper::cpUrl(
                            'passwordprotection/settings'
                        ))->send();
                    }
                }
            }
        );
    }

    /**
     * Registers Twig extensions.
     */
    private function _registerTwigExtensions()
    {
        Craft::$app->view->registerTwigExtension(new LoginExtensions());
    }

    /**
     * Registers custom variables
     */
    private function _registerVariables()
    {
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function (Event $e) {
                $variable = $e->sender;
                $variable->set("entryPassword", PasswordVariables::class);
            }
        );
    }

    /**
     * Registers Routes
     */
    private function _registerRoutes()
    {
        Event::on(
			UrlManager::class,
			UrlManager::EVENT_REGISTER_CP_URL_RULES,
			function (RegisterUrlRulesEvent $event) {
				// Register our Control Panel routes
				$event->rules = array_merge(
					$event->rules,
					$this->customAdminCpRoutes()
				);
			}
		);
    }

    /**
     * Registers Templates Route
     */
    private function _registerTemplates()
    {
        Event::on(
			View::class,
			View::EVENT_REGISTER_CP_TEMPLATE_ROOTS,
			function (RegisterTemplateRootsEvent $e) {
				if (
					is_dir(
						$baseDir =
							$this->getBasePath() .
							DIRECTORY_SEPARATOR .
							"templates"
					)
				) {
					$e->roots[$this->id] = $baseDir;
				}
			}
		);
    }

    /**
	 * Registers Permissions
	 */
	private function _registerPermissions(): void
	{
        Event::on(
            UserPermissions::class,
            UserPermissions::EVENT_REGISTER_PERMISSIONS,
            function(RegisterUserPermissionsEvent $event) {

                $includedPasswordSections = self::$settings->includedSections;

                if (!empty($includedPasswordSections))
                {
                    $permissions = [];

                    foreach($includedPasswordSections as $id)
                    {
                        $section = Craft::$app->entries->getSectionById($id);

                        $permissions["passwordprotection:section-$id"] = [
                            'label' => Craft::t('passwordprotection', "View Password on $section->name"),
                            'nested' => [
                                "passwordprotection:section-$id:edit" => [
                                    'label' => Craft::t('passwordprotection', "Edit Password on $section->name"),
                                ],
                            ],
                        ];
                    };
                    $event->permissions[] = [
                        'heading' => 'Password Protection',
                        'permissions' => $permissions,
                    ];
                }
            }
        );
    }

    /**
	 * Registers sidebar meta box
	 */
	private function _registerSidebar(): void
	{
		Event::on(
            Element::class,
            Element::EVENT_DEFINE_SIDEBAR_HTML,
            function (DefineHtmlEvent $event)
        {
			/** @var Element $element */
			$element = $event->sender;

			// We only support entries with urls
			if (!$element instanceof Entry or !$element->url) {
				return;
			}

            // Exclude sections which are selected in the settings
            $included = self::$settings->includedSections;
            if (!in_array($element->section->id, $included, true))
            {
                return;
            }

            // Check user permissions
            $currentUser = Craft::$app->getUser()->getIdentity();

            // Check if user can view passwords on this section
            if ($currentUser->can("passwordprotection:section-{$element->section->id}")) {

                // Get Password from DB against Entry
                $password = self::$instance->passwordEntry->get($element->canonicalId);

                // Add Password Block
                $html = "";
			    $html .= Craft::$app->view->renderTemplate(
				    "passwordprotection/_sidebar/password",
                    [
                        "password" => $password,
                        "readonly" => $currentUser->can("passwordprotection:section-{$element->section->id}:edit") ? false : true,
                    ]
			    );
			    $html .= $event->html;
			    $event->html = $html;

            }
		});
	}

    /**
     * Save the password after entry save
     */
    private function _savePassword()
    {
        Event::on(
            Entry::class,
            Entry::EVENT_AFTER_SAVE,
            static function (ModelEvent $event)
        {

            /** Entry @entry */
            $element = $event->sender;

            // We only support entries
			if (!$element instanceof Entry) {
				return;
			}

            $id = Craft::$app->getRequest()->getBodyParam("elementId");
            $password = Craft::$app->getRequest()->getBodyParam("entry-password");

            if (isset($password))
            {
                self::$instance->passwordEntry->register($id, $password);

                // If Blitz is installed, we want to refresh the cache on this page
                $blitzPlugin = Craft::$app->plugins->getPlugin('blitz', false);

                if ($blitzPlugin) {

                    $entries = Entry::find()
                        ->site('*')
                        ->id($id)
                        ->all();

                    $urls = [];

                    foreach ($entries as $entry)
                    {
                        array_push($urls, $entry->url);
                    }

                    // Regenerate Blitz for this page
                    Blitz::$plugin->refreshCache->refreshCachedUrls($urls);
                }
            }
        });

    }

    // Protected Methods
	// =========================================================================

	/**
	 * @inheritdoc
	 */
	protected function createSettingsModel(): ?Model
	{
		return new Settings();
	}

	protected function settingsHtml(): string
	{
		return Craft::$app
			->getView()
			->renderTemplate("passwordprotection/settings", [
				"settings" => $this->getSettings(),
			]);
	}

	protected function customAdminCpRoutes(): array
	{
        return [
			"passwordprotection" => [
				"template" => "passwordprotection/settings",
			],
			"passwordprotection/settings" =>
				"passwordprotection/settings/plugin-settings",
			'passwordprotection/settings/<subSection:{handle}>' =>
                "passwordprotection/settings/plugin-settings",
            $this->getRoute() =>
                "password/sessions/create"
		];
	}


}
