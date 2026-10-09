<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApplicationMetricsRequest extends FormRequest
{
    public const string RANGE_ALL = 'all';

    public const string RANGE_CUSTOM = 'custom';

    private const string RANGE_YEAR_TO_DATE = 'ytd';

    /**
     * The rolling presets, as the number of days before today each starts.
     *
     * @var array<string, int>
     */
    private const array ROLLING_PRESETS = [
        '30d' => 30,
        '90d' => 90,
    ];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'range' => ['nullable', 'string', Rule::in([...array_keys(self::ROLLING_PRESETS), self::RANGE_YEAR_TO_DATE, self::RANGE_ALL])],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', ...($this->filled('from') ? ['after_or_equal:from'] : [])],
        ];
    }

    /**
     * Get the custom error messages for the defined rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'range.in' => 'Choose one of the listed periods.',
            'from.date_format' => 'The start date must be a date in YYYY-MM-DD form.',
            'to.date_format' => 'The end date must be a date in YYYY-MM-DD form.',
            'to.after_or_equal' => 'The end date cannot be before the start date.',
        ];
    }

    /**
     * The period the dashboard covers. A `from` or `to` overrides any preset
     * and is reported as a custom range; a preset is resolved against today.
     * Either end is null when it is open.
     *
     * @return array{range: string, from: ?CarbonInterface, to: ?CarbonInterface}
     */
    public function period(): array
    {
        $from = $this->validated('from');
        $to = $this->validated('to');

        if ($from !== null || $to !== null) {
            return [
                'range' => self::RANGE_CUSTOM,
                'from' => $from !== null ? CarbonImmutable::createFromFormat('!Y-m-d', $from) : null,
                'to' => $to !== null ? CarbonImmutable::createFromFormat('!Y-m-d', $to) : null,
            ];
        }

        $range = $this->validated('range') ?? self::RANGE_ALL;
        $today = CarbonImmutable::today();

        return match (true) {
            isset(self::ROLLING_PRESETS[$range]) => [
                'range' => $range,
                'from' => $today->subDays(self::ROLLING_PRESETS[$range]),
                'to' => $today,
            ],
            $range === self::RANGE_YEAR_TO_DATE => [
                'range' => $range,
                'from' => $today->startOfYear(),
                'to' => $today,
            ],
            default => ['range' => self::RANGE_ALL, 'from' => null, 'to' => null],
        };
    }

    /**
     * The active period as the page receives it.
     *
     * @return array{range: string, from: ?string, to: ?string}
     */
    public function filter(): array
    {
        $period = $this->period();

        return [
            'range' => $period['range'],
            'from' => $period['from']?->toDateString(),
            'to' => $period['to']?->toDateString(),
        ];
    }
}
