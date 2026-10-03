<?php

namespace Modules\Reports\Services;

use Illuminate\Contracts\Container\Container;
use Modules\Reports\Contracts\Report;

final class ReportRegistry
{
    /** @var array<string, Report>|null */
    private ?array $resolved = null;

    /**
     * @param  list<class-string<Report>>  $classes
     */
    public function __construct(private readonly Container $container, private readonly array $classes) {}

    /**
     * @return array<string, Report>
     */
    public function all(): array
    {
        if ($this->resolved === null) {
            $this->resolved = [];

            foreach ($this->classes as $class) {
                $report = $this->container->make($class);
                $this->resolved[$report->key()] = $report;
            }
        }

        return $this->resolved;
    }

    public function find(string $key): ?Report
    {
        return $this->all()[$key] ?? null;
    }
}
