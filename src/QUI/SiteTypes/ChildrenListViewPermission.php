<?php

namespace QUI\SiteTypes;

use QUI;
use QUI\Interfaces\Users\User;
use QUI\Permissions\Permission;

/**
 * Evaluate the rules loaded with each candidate batch to avoid loading a Site for every entry.
 * Replace this local handling of Core defaults when quiqqer/core#1585 is available.
 */
class ChildrenListViewPermission extends Permission
{
    public const SITE_VIEW = 'quiqqer.projects.site.view';

    private readonly User $Actor;
    private readonly bool $unrestricted;
    private readonly bool $definedForSites;
    private ?bool $default = null;

    /** @var array<array-key, bool> */
    private array $grants = [];

    public function __construct()
    {
        $this->Actor = parent::getUser();
        $this->unrestricted = $this->Actor->isSU() || QUI::getUsers()->isSystemUser($this->Actor);

        $this->definedForSites = !$this->unrestricted
            && isset(QUI::getPermissionManager()->getPermissionList('site')[self::SITE_VIEW]);
    }

    public function allows(bool $hasRule, mixed $rule): bool
    {
        if ($this->unrestricted) {
            return true;
        }

        if (($hasRule && $rule === null) || (!$hasRule && !$this->definedForSites)) {
            return true;
        }

        if (empty($rule)) {
            return $this->default ??= (bool)parent::hasPermission('quiqqer.projects.sites.view', $this->Actor);
        }

        $key = (string)$rule;

        if (!isset($this->grants[$key])) {
            try {
                $this->grants[$key] = parent::checkPermissionList(
                    [self::SITE_VIEW => $rule],
                    self::SITE_VIEW,
                    $this->Actor
                );
            } catch (QUI\Permissions\Exception) {
                $this->grants[$key] = false;
            }
        }

        return $this->grants[$key];
    }
}
