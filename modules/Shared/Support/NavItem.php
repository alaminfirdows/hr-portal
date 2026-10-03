<?php

namespace Modules\Shared\Support;

/**
 * A sidebar navigation entry contributed by a module.
 */
final readonly class NavItem
{
    /**
     * @param  string|list<string>|null  $permission  Any of these permission keys grants visibility.
     */
    public function __construct(
        public string $label,
        public string $route,
        public string $icon,
        public string|array|null $permission = null,
        public string $group = 'General',
        public int $order = 100,
        public ?string $activePattern = null,
    ) {}

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        return $this->permission === null ? [] : (is_array($this->permission) ? $this->permission : [$this->permission]);
    }
}
