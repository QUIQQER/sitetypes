<?php

/**
 * This file contains QUI\Controls\ExternalContent
 */

namespace QUI\Controls;

use Exception;
use QUI;

use function dirname;

/**
 * Class ExternalContent
 */
class ExternalContent extends QUI\Control
{
    /**
     * constructor
     *
     * @param array<string, mixed> $attributes
     */
    public function __construct(array $attributes = [])
    {
        // default options
        $this->setAttributes([
            'class' => 'quiqqer-sitetypes-controls-externalContent',
            'externalContentType' => 'text', // 'text' or 'iframe'
            'externalContentText' => '', // it could be a script tag and / or HTML Node <div>
            'iframeUrl' => '',
            'iFrameHeightDesktop' => 400,
            'iFrameHeightMobile' => '',
            'iFrameWidth' => '100%'
        ]);

        parent::__construct($attributes);

        $this->addCSSFile(dirname(__FILE__) . '/ExternalContent.css');

        $this->setAttribute('cacheable', 0);
    }

    /**
     * Return the inner body of the element
     * Can be overwritten
     *
     * @return string
     *
     * @throws Exception
     */
    public function getBody(): string
    {
        $Engine = QUI::getTemplateManager()->getEngine();

        $type = $this->getAttribute('externalContentType');
        $externalContentText = $this->getAttribute('externalContentText');
        $iframeUrl = $this->getAttribute('iframeUrl');

        if (!$externalContentText && !$iframeUrl) {
            QUI\System\Log::addWarning('QUI\Controls\ExternalContent: nor externalContentText or iframeUrl found.');

            return '';
        }

        $heightDesktop = $this->getAttribute('iFrameHeightDesktop') ?: '400px';
        $heightMobile = $this->getAttribute('iFrameHeightMobile');
        $width = $this->getAttribute('iFrameWidth') ?: '100%';

        if (!$heightMobile) {
            $heightMobile = $heightDesktop;
        }

        $this->setCustomVariable('iframe-height--desktop', $this->getValue($heightDesktop));
        $this->setCustomVariable('iframe-height--mobile', $this->getValue($heightMobile));
        $this->setCustomVariable('iframe-width', $this->getValue($width));
        $this->setCustomVariable('iframe-width', $this->getValue($width));

        $Engine->assign([
            'this' => $this,
            'type' => $type,
            'externalContentText' => $externalContentText,
            'iframeUrl' => $iframeUrl
        ]);

        $template = $this->getTemplateFile();

        if (!is_string($template) || $template === '') {
            QUI\System\Log::addWarning('QUI\\Controls\\ExternalContent: no valid template file found.');

            return '';
        }

        return $Engine->fetch($template);
    }

    /**
     * Check if $value has a unit, if not add px
     *
     * @param mixed $value
     * @return string
     */
    protected function getValue(mixed $value): string
    {
        if (
            is_int($value) ||
            is_float($value) ||
            (is_string($value) && is_numeric($value))
        ) {
            $value = $value . 'px';
        }

        return (string)$value;
    }

    /**
     * Set custom css variable to the control as inline style
     *   --_qui-sitetypes-controls-externalContent-$name: var(--qui-sitetypes-controls-externalContent-$name, $value);
     *
     * Example:
     *   --_qui-sitetypes-controls-externalContent-iFrameHeight--desktop: var(--qui-sitetypes-controls-externalContent-iFrameHeight--desktop, '50vh');
     *
     * @param string $name
     * @param string $value
     *
     * @return void
     */
    private function setCustomVariable(string $name, string $value): void
    {
        if (!$name || !$value) {
            return;
        }

        $this->setStyle(
            '--_qui-sitetypes-controls-externalContent-' . $name,
            'var(--qui-sitetypes-controls-externalContent-' . $name . ', ' . $value . ')'
        );
    }
}
