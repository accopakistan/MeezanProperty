<?php

namespace App\Services\Agents;

use App\Models\AiAgent;
use Illuminate\Validation\Rules\In;

/**
 * An agent's permitted tools as Gemini function declarations (name,
 * description, parametersJsonSchema). The schema is derived from each tool's
 * Laravel validation rules so the model sees the same limits; the rules
 * themselves are still applied server-side by ToolRunner on every call.
 * Prohibited tools are never declared.
 */
class ToolDeclarations
{
    public const DELEGATE = 'agents.delegate';

    public function __construct(protected ToolRegistry $registry) {}

    /** @return list<array{name: string, description: string, parametersJsonSchema: array}> */
    public function forAgent(AiAgent $agent): array
    {
        $declarations = collect($this->registry->all())
            ->filter(fn (ToolDefinition $t) => $t->risk !== 'prohibited' && $agent->canUse($t->name))
            ->map(fn (ToolDefinition $t) => [
                'name' => $t->name,
                'description' => mb_substr($t->description.($t->risk === 'approval' ? ' (Creates a request for staff approval; nothing is done until staff approve.)' : ''), 0, 1000),
                'parametersJsonSchema' => static::schema($t->rules),
            ])->values()->all();

        if (AgentCatalog::canDelegate($agent->slug)) {
            $others = AiAgent::where('status', 'active')->where('slug', '!=', $agent->slug)->orderBy('id')->pluck('slug')->all();
            $declarations[] = ['name' => self::DELEGATE, 'description' => 'Ask another Meezan agent to carry out part of the task through the Central AI Brain. Returns that agent\'s report.',
                'parametersJsonSchema' => ['type' => 'object', 'properties' => [
                    'agent' => ['type' => 'string', 'enum' => $others, 'description' => 'The agent to ask.'],
                    'request' => ['type' => 'string', 'maxLength' => 1000, 'description' => 'What that agent should do.'],
                ], 'required' => ['agent', 'request']]];
        }

        return $declarations;
    }

    /** JSON Schema for a tool's input, from its validation rules. */
    public static function schema(array $rules): array
    {
        $properties = [];
        $required = [];
        foreach ($rules as $field => $fieldRules) {
            if (str_contains((string) $field, '.')) {
                continue; // nested rules (items.*) are described by their parent
            }
            $fieldRules = is_array($fieldRules) ? $fieldRules : explode('|', (string) $fieldRules);
            $strings = collect($fieldRules)->filter(fn ($r) => is_string($r))->map(fn ($r) => strtolower($r));
            $names = $strings->map(fn ($r) => strtok($r, ':'))->all();

            $type = match (true) {
                in_array('integer', $names, true) || $strings->contains(fn ($r) => str_starts_with($r, 'exists:')) => 'integer',
                in_array('numeric', $names, true) => 'number',
                in_array('boolean', $names, true) => 'boolean',
                in_array('array', $names, true) => 'array',
                default => 'string',
            };
            $p = ['type' => $type];
            if ($type === 'array') {
                $p['items'] = ['type' => 'string'];
            }
            foreach ($strings as $rule) {
                [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);
                $numeric = in_array($type, ['integer', 'number'], true);
                match ($name) {
                    'min' => $p[$numeric ? 'minimum' : ($type === 'array' ? 'minItems' : 'minLength')] = (int) $arg,
                    'max' => $p[$numeric ? 'maximum' : ($type === 'array' ? 'maxItems' : 'maxLength')] = (int) $arg,
                    'between' => [$p[$numeric ? 'minimum' : 'minLength'], $p[$numeric ? 'maximum' : 'maxLength']] = array_map('intval', explode(',', (string) $arg)),
                    'in' => $p['enum'] = static::enumValues($arg),
                    'email' => $p['format'] = 'email',
                    'date_format' => $p['description'] = 'Date in the format '.$arg.'.',
                    'exists' => $p['description'] = 'The id of an existing record.',
                    default => null,
                };
            }
            foreach ($fieldRules as $rule) {
                if ($rule instanceof In) {
                    $p['enum'] = static::enumValues(substr((string) $rule, 3));
                }
            }
            if (isset($p['enum']) && $type !== 'string') {
                unset($p['enum']); // only string enums are declared
            }
            if (in_array('required', $names, true)) {
                $required[] = (string) $field;
            }
            $properties[(string) $field] = $p;
        }

        return ['type' => 'object', 'properties' => (object) $properties] + ($required ? ['required' => $required] : []);
    }

    /** Values of an "in" rule: in:a,b or in:"a","b". */
    protected static function enumValues(?string $arg): array
    {
        return collect(str_getcsv((string) $arg, ',', '"', '\\'))->map(fn ($v) => trim($v, '"'))->filter(fn ($v) => $v !== '')->values()->all();
    }
}
