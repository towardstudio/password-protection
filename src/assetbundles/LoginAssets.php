<?php

namespace towardstudio\passwordprotection\assetbundles;

use craft\web\AssetBundle as CraftAssetBundle;

class LoginAssets extends CraftAssetBundle
{
    public function init()
    {
        $this->sourcePath = '@passwordprotection/resources';

        $this->css = [
            'css/styles.css',
        ];

        parent::init();
    }
}
