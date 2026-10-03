<?php

namespace Modules\Settings\Data;

final readonly class OrganizationSettings
{
    public const DEFAULTS = [
        'company_name' => 'Acme Inc.',
        'timezone' => 'UTC',
        'currency' => 'USD',
        'fiscal_year_start_month' => 1,
        'leave_year_start_month' => 1,
        'working_days' => [1, 2, 3, 4, 5],
        'date_format' => 'd M Y',
    ];

    /**
     * @param  list<int>  $workingDays  ISO-8601 day numbers (1 = Monday)
     */
    public function __construct(
        public string $companyName,
        public string $timezone,
        public string $currency,
        public int $fiscalYearStartMonth,
        public int $leaveYearStartMonth,
        public array $workingDays,
        public string $dateFormat,
    ) {}

    /**
     * @param  array<string, mixed>  $values
     */
    public static function fromArray(array $values): self
    {
        /** @var list<int> $days */
        $days = array_values(array_map('intval', (array) $values['working_days']));

        return new self(
            companyName: (string) $values['company_name'],
            timezone: (string) $values['timezone'],
            currency: (string) $values['currency'],
            fiscalYearStartMonth: (int) $values['fiscal_year_start_month'],
            leaveYearStartMonth: (int) $values['leave_year_start_month'],
            workingDays: $days,
            dateFormat: (string) $values['date_format'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'company_name' => $this->companyName,
            'timezone' => $this->timezone,
            'currency' => $this->currency,
            'fiscal_year_start_month' => $this->fiscalYearStartMonth,
            'leave_year_start_month' => $this->leaveYearStartMonth,
            'working_days' => $this->workingDays,
            'date_format' => $this->dateFormat,
        ];
    }
}
