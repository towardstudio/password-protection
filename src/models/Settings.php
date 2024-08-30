<?php
namespace towardstudio\passwordprotection\models;

use towardstudio\passwordprotection\PasswordProtection;

use Craft;
use craft\base\Model;

class Settings extends Model
{
	// Public Properties
	// =========================================================================

	/**
	 * @var string
	 */

	public $name = "Password Protection";
	public $cookieLife = 3600;
	public $maxLogin = 5;
	public $maxLoginsPeriod = 300;
    public array $includedSections = [];

	// Public Methods
	// =========================================================================

	/**
	 * @inheritdoc
	 */
	public function rules(): array
	{
		return [
			[
                [
                    "cookieLife",
                    "maxLogin",
                    "maxLoginsPeriod"
                ],
                "number"
            ],
		];
	}
}
