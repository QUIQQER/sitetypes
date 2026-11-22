<?php

namespace QUI\SiteTypes;

use QUI;
use QUI\Bricks\Controls\Children\Listing;
use QUI\Controls\ChildrenList;
use QUI\Interfaces\Projects\Site;
use QUI\Projects\Site\PermissionDenied;
use QUI\System\VhostManager;

final class ChildrenListRequest
{
    /**
     * @return array{html: string}|null
     */
    public static function render(
        mixed $project,
        mixed $lang,
        mixed $siteId,
        mixed $placement,
        mixed $filter
    ): ?array {
        if (
            !defined('QUIQQER_FRONTEND')
            || !QUIQQER_FRONTEND
            || !is_string($project)
            || !is_string($lang)
            || !is_scalar($siteId)
            || !ctype_digit((string)$siteId)
            || (int)$siteId < 1
            || !is_string($placement)
            || !is_string($filter)
        ) {
            return null;
        }

        $context = json_decode($project, true);
        $payload = json_decode($filter, true);

        if (
            !is_array($context)
            || !is_string($context['name'] ?? null)
            || ($context['lang'] ?? null) !== $lang
            || !is_array($payload)
        ) {
            return null;
        }

        try {
            $Project = QUI::getProject($context['name'], $lang);
            if (
                $Project->getLang() !== $lang
                || !self::allowsHost(
                    QUI::getRequest()->getHttpHost(),
                    $Project->getName(),
                    $lang,
                    QUI::vhosts(),
                    [(string)QUI::conf('globals', 'host') => ['httpshost' => QUI::conf('globals', 'httpshost')]]
                )
            ) {
                return null;
            }

            $Site = $Project->get((int)$siteId);
        } catch (QUI\Exception) {
            return null;
        }

        $Control = self::resolve($Site, $placement);
        if ($Control === null) {
            return null;
        }

        $Rewrite = QUI::getRewrite();
        $PreviousSite = $Rewrite->getSite();
        $Rewrite->setSite($Site);

        try {
            $Output = new QUI\Output();
            $Output->setProject($Project);
            $fragment = $Control->renderFilterFragment([
                'q' => $payload['q'] ?? '',
                'tag' => $payload['tag'] ?? '',
                'page' => $payload['page'] ?? 1
            ]);

            return ['html' => $Output->parse($fragment)];
        } finally {
            if ($PreviousSite !== null) {
                $Rewrite->setSite($PreviousSite);
            }
        }
    }

    public static function resolve(Site $Site, string $placement): ?ChildrenList
    {
        if ($Site instanceof PermissionDenied || !(int)$Site->getAttribute('active')) {
            return null;
        }

        if ($placement === 'site') {
            if ($Site->getAttribute('type') !== ChildrenListPage::TYPE) {
                return null;
            }
            $Site->load();
            $Control = ChildrenListPage::create($Site);
        } elseif (preg_match('/^brick-([a-zA-Z0-9-]+)$/D', $placement, $matches)) {
            $resolvePlacement = self::getBrickPlacementResolver(Listing::class);
            if ($resolvePlacement === null) {
                return null;
            }
            $Control = $resolvePlacement($Site, $matches[1]);
        } else {
            return null;
        }

        return $Control instanceof ChildrenList && $Control->isFilterEnabled() ? $Control : null;
    }

    public static function getPlacement(mixed $placement): string
    {
        if ($placement === 'site') {
            return 'site';
        }

        if (
            is_string($placement)
            && str_starts_with($placement, 'brick-')
            && self::getBrickPlacementResolver(Listing::class) !== null
        ) {
            return $placement;
        }

        return '';
    }

    /**
     * @param class-string $listingClass
     * @return (callable(Site, string): ?ChildrenList)|null
     */
    private static function getBrickPlacementResolver(string $listingClass): ?callable
    {
        $resolver = [$listingClass, 'fromPlacement'];

        return is_callable($resolver) ? $resolver : null;
    }

    /**
     * Check the requested host against the project's configured VHost routes.
     *
     * @param array<array-key, mixed> $vhosts
     * @param array<string, mixed> $installationHosts
     */
    public static function allowsHost(
        string $requestHost,
        string $project,
        string $language,
        array $vhosts,
        array $installationHosts
    ): bool {
        $host = VhostManager::findVhost($requestHost, $vhosts);
        $route = $host === null ? null : $vhosts[$host];

        if (is_array($route) && ($route['project'] ?? null) !== $project) {
            return false;
        }

        // Several configured hosts may serve the same project and language.
        if (is_array($route)) {
            $local = VhostManager::resolveProjectLanguageRoute([$host => $route], $project, $language);

            if ($local !== null && VhostManager::findVhost($requestHost, [$local['host'] => $local]) !== null) {
                return true;
            }
        }

        $assigned = VhostManager::resolveProjectLanguageRoute($vhosts, $project, $language);

        if ($assigned !== null) {
            return VhostManager::findVhost($requestHost, [$assigned['host'] => $assigned]) !== null;
        }

        // Unassigned languages are served by their project's host or the installation host.
        return $host !== null || VhostManager::findVhost($requestHost, $installationHosts) !== null;
    }
}
