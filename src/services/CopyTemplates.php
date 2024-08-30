<?php

namespace towardstudio\passwordprotection\services;

use Craft;
use craft\helpers\UrlHelper;

use towardstudio\passwordprotection\Password;

use yii\base\Component;
use yii\web\Cookie;

use InvalidArgumentException;
use Exception;

class CopyTemplates extends Component
{

    public function copyDirectory($directory, $destination)
    {
	    # The directory will be created if it doesn't already exist
	    if (!file_exists($destination)) {
		    if (!mkdir($destination)) {
			    return false;
		    }
	    } else {
		    throw new Exception('Folder already exists');
	    }

	    $directoryList = @scandir($directory);

	    # Directory scanning
	    if (!$directoryList) {
		    return false;
	    }

	    foreach ($directoryList as $itemName) {
		    $item = $directory . DIRECTORY_SEPARATOR . $itemName;

		    if ($itemName == '.' || $itemName == '..') {
			    continue;
		    }

		    if (filetype($item) == 'dir') {
			    $this->copyDirectory($item, $destination . DIRECTORY_SEPARATOR . '/' . $itemName);
		    } else {
			    if (!copy($item, $destination . DIRECTORY_SEPARATOR . $itemName)) {
				    return false;
			    }
		    }
	    }
	    return true;
    }

}
