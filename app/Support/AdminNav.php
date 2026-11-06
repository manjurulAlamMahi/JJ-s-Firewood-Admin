<?php

namespace App\Support;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * Resolves config/admin_nav.php into something the shell can render.
 *
 * The sidebar, the mobile drawer and the bottom tab bar all read from here, so
 * a navigation change is a one-line edit in the config rather than three.
 */
class AdminNav
{
    /** @var array<string,bool> */
    protected static array $tableCache = [];

    /** @var array<string,int|null> */
    protected static array $badgeCache = [];

    /**
     * Top-level groups with unresolvable items already stripped out.
     */
    public static function groups(): array
    {
        $groups = [];

        foreach (config('admin_nav.groups', []) as $group) {
            $items = [];

            foreach ($group['items'] ?? [] as $item) {
                $item = self::resolve($item);
                if ($item !== null) {
                    $items[] = $item;
                }
            }

            if ($items !== []) {
                $groups[] = ['label' => $group['label'] ?? '', 'items' => $items];
            }
        }

        return $groups;
    }

    /**
     * Destinations for the mobile bottom tab bar.
     */
    public static function primary(): array
    {
        $items = [];

        foreach (config('admin_nav.primary', []) as $item) {
            $item = self::resolve($item);
            if ($item !== null) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * Turn a config entry into a renderable item, or null when it cannot be
     * shown on this deployment (missing route, missing table, no children).
     */
    protected static function resolve(array $item): ?array
    {
        if (! empty($item['requires']) && ! self::hasTable($item['requires'])) {
            return null;
        }

        $children = [];

        foreach ($item['children'] ?? [] as $child) {
            $child = self::resolve($child);
            if ($child !== null) {
                $children[] = $child;
            }
        }

        $url = self::url($item);

        // A parent whose children all disappeared is dead weight.
        if ($url === null && $children === []) {
            return null;
        }

        $item['url'] = $url;
        $item['children'] = $children;
        $item['active'] = self::isActive($item);
        $item['badge_count'] = isset($item['badge']) ? self::badge($item['badge']) : null;

        // Powers the drawer's search box without re-walking the tree in JS.
        $item['search'] = strtolower(trim(
            ($item['label'] ?? '') . ' ' . collect($children)->pluck('label')->implode(' ')
        ));

        return $item;
    }

    protected static function url(array $item): ?string
    {
        $url = null;

        if (! empty($item['route'])) {
            // Routes come and go between branches; a missing one hides the link
            // rather than throwing a RouteNotFoundException in the layout.
            if (! Route::has($item['route'])) {
                return null;
            }

            try {
                $url = route($item['route']);
            } catch (Throwable $e) {
                return null;
            }
        } elseif (! empty($item['url'])) {
            $url = url($item['url']);
        }

        if ($url !== null && ! empty($item['params'])) {
            $url .= (str_contains($url, '?') ? '&' : '?') . $item['params'];
        }

        return $url;
    }

    protected static function isActive(array $item): bool
    {
        foreach ($item['children'] ?? [] as $child) {
            if (! empty($child['active'])) {
                return true;
            }
        }

        $current = Route::currentRouteName() ?? '';
        $needles = $item['match'] ?? array_filter([$item['route'] ?? null]);

        foreach ($needles as $needle) {
            if ($current === $needle || ($current !== '' && Str::startsWith($current, $needle))) {
                return true;
            }
        }

        // Fall back to the path for links declared with a raw `url`.
        if (empty($item['route']) && ! empty($item['url'])) {
            $path = '/' . ltrim(request()->path(), '/');
            $target = '/' . ltrim(parse_url($item['url'], PHP_URL_PATH) ?? '', '/');

            if ($target !== '/' && Str::startsWith($path, $target)) {
                return true;
            }
        }

        return false;
    }

    protected static function hasTable(string $table): bool
    {
        if (! array_key_exists($table, self::$tableCache)) {
            try {
                self::$tableCache[$table] = Schema::hasTable($table);
            } catch (Throwable $e) {
                // The menu must render even when the database is unreachable.
                self::$tableCache[$table] = false;
            }
        }

        return self::$tableCache[$table];
    }

    /**
     * Counts shown as a pill next to a nav item. Failures resolve to null so a
     * broken counter never takes the whole layout down.
     */
    public static function badge(string $key): ?int
    {
        if (array_key_exists($key, self::$badgeCache)) {
            return self::$badgeCache[$key];
        }

        $value = null;

        try {
            if ($key === 'setmore_pending' && self::hasTable('setmore_webhook_events')) {
                $value = app(\App\Services\SetmoreMatchingService::class)->pendingCount();
            }
        } catch (Throwable $e) {
            $value = null;
        }

        return self::$badgeCache[$key] = ($value > 0 ? (int) $value : null);
    }
}
