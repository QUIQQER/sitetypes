<?php

/**
 * This file contains QUI\Controls\InlineFrame
 */

namespace QUI\Controls;

use Exception;
use QUI;
use QUI\Projects\Site\Utils;

use function ceil;
use function count;
use function dirname;
use function file_exists;
use function is_array;

/**
 * Class InlineFrame
 *
 * @package quiqqer/sitetypes
 */
class InlineFrame extends QUI\Control
{
    /**
     * constructor
     *
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        // default options
        $this->setAttributes([
            'class' => 'quiqqer-sitetypes-controls-inlineframe',
            'url' => '',
            'iFrameHeightDesktop' => 400,
            'iFrameHeightMobile' => '',
            'iFrameWidth' => '100%'
        ]);

        parent::__construct($attributes);

        $this->addCSSFile(dirname(__FILE__) . '/InlineFrame.css');

        $this->setAttribute('cacheable', 0);
    }

    /**
     * Return the inner body of the element
     * Can be overwritten
     *
     * @return String
     *
     * @throws QUI\Exception
     * @throws Exception
     */
    public function getBody(): string
    {
        $Engine = QUI::getTemplateManager()->getEngine();

        if (!$this->getAttribute('url')) {
            return '';
        }

        $heightDesktop = $this->getAttribute('iFrameHeightDesktop') ?: '400px';
        $heightMobile = $this->getAttribute('iFrameHeightMobile');
        $width = $this->getAttribute('iFrameWidth') ?: '100%';

        if (!$heightMobile) {
            $heightMobile = $heightDesktop;
        }

        $this->setCustomVariable('height--desktop', $this->getValue($heightDesktop));
        $this->setCustomVariable('height--mobile', $this->getValue($heightMobile));
        $this->setCustomVariable('width', $this->getValue($width));

        $Engine->assign([
            'this' => $this,
            'url' => $this->getAttribute('url')
        ]);

        return $Engine->fetch($this->getTemplateFile());
    }

    /**
     * Check if $value has a unit, if not add px
     *
     * @param $value
     * @return string
     */
    protected function getValue($value): string
    {
        if (
            is_int($value) ||
            is_float($value) ||
            (is_string($value) && is_numeric($value))
        ) {
            $value = $value . 'px';
        }

        return $value;
    }

    /**
     * Set custom css variable to the control as inline style
     *   --_qui-sitetypes-controls-inlineFrame-$name: var(--qui-sitetypes-controls-inlineFrame-$name, $value);
     *
     * Example:
     *   --_qui-sitetypes-controls-inlineFrame-iFrameHeight--desktop: var(--qui-sitetypes-controls-inlineFrame-iFrameHeight--desktop, '50vh');
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
            '--_qui-sitetypes-controls-inlineFrame-' . $name,
            'var(--qui-sitetypes-controls-inlineFrame-' . $name . ', ' . $value . ')'
        );
    }
}
