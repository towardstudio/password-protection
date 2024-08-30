<?php

namespace  towardstudio\passwordprotection\twigextensions;

use Craft;
use craft\helpers\Cp;
use craft\web\View;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class LoginExtensions extends AbstractExtension {

    public function getFunctions(): array
    {
        return [
            new TwigFunction('field', [$this, 'renderFormField'], ['is_safe' => ['html']])
        ];
    }

    public function renderFormField(string $fieldType, array $fieldOptions) : string
    {
        return Cp::fieldHtml('template:_includes/forms/' . $fieldType, $fieldOptions);
    }
}
