<?php

namespace App\Support\Crud;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Declarative field definition used by CrudResource for forms, tables,
 * filters, validation and exports.
 */
class Field
{
    public string $type = 'text';
    public ?string $label = null;
    public array $rules = [];
    public bool $required = false;
    public bool $inIndex = false;
    public bool $onForm = true;
    public bool $onShow = true;
    public bool $sortable = false;
    public bool $searchable = false;
    public bool $filterable = false;
    public int $col = 6;
    public mixed $options = null;
    public ?string $lookup = null;
    public ?string $relation = null;
    public ?string $relationAttr = null;
    public mixed $default = null;
    public ?string $help = null;
    public ?Closure $displayUsing = null;
    public bool $money = false;
    public ?string $uniqueTable = null;
    public bool $global = false;
    public ?string $section = null;
    public array $attrs = [];

    public function __construct(public string $name) {}

    public static function make(string $name, string $type = 'text'): static
    {
        $f = new static($name);
        $f->type = $type;

        return $f;
    }

    public static function text(string $n): static { return static::make($n, 'text'); }
    public static function textarea(string $n): static { return static::make($n, 'textarea')->col(12); }
    public static function email(string $n): static { return static::make($n, 'email'); }
    public static function tel(string $n): static { return static::make($n, 'tel'); }
    public static function number(string $n): static { return static::make($n, 'integer'); }
    public static function decimal(string $n): static { return static::make($n, 'decimal'); }
    public static function money(string $n): static { $f = static::make($n, 'decimal'); $f->money = true; return $f; }
    public static function date(string $n): static { return static::make($n, 'date'); }
    public static function datetime(string $n): static { return static::make($n, 'datetime'); }
    public static function time(string $n): static { return static::make($n, 'time'); }
    public static function checkbox(string $n): static { return static::make($n, 'checkbox'); }
    public static function color(string $n): static { return static::make($n, 'color'); }
    public static function image(string $n): static { return static::make($n, 'image'); }
    public static function password(string $n): static { return static::make($n, 'password')->hideOnShow(); }

    public static function select(string $n, array|Closure $options): static
    {
        return static::make($n, 'select')->options($options);
    }

    /** Remote searchable select (see LookupController). */
    public static function lookup(string $n, string $lookup, ?string $relation = null, string $attr = 'name'): static
    {
        $f = static::make($n, 'lookup');
        $f->lookup = $lookup;
        $f->relation = $relation;
        $f->relationAttr = $attr;

        return $f;
    }

    public function label(string $l): static { $this->label = $l; return $this; }
    public function required(bool $r = true): static { $this->required = $r; return $this; }
    public function rules(array $r): static { $this->rules = array_merge($this->rules, $r); return $this; }
    public function index(bool $sortable = true): static { $this->inIndex = true; $this->sortable = $sortable; return $this; }
    public function searchable(): static { $this->searchable = true; return $this; }
    public function filterable(): static { $this->filterable = true; return $this; }
    public function hideOnForm(): static { $this->onForm = false; return $this; }
    public function hideOnShow(): static { $this->onShow = false; return $this; }
    public function col(int $c): static { $this->col = $c; return $this; }
    public function options(array|Closure $o): static { $this->options = $o; return $this; }
    public function default(mixed $d): static { $this->default = $d; return $this; }
    public function help(string $h): static { $this->help = $h; return $this; }
    public function section(string $s): static { $this->section = $s; return $this; }
    public function attrs(array $a): static { $this->attrs = $a; return $this; }
    public function display(Closure $c): static { $this->displayUsing = $c; return $this; }

    public function relation(string $relation, string $attr = 'name'): static
    {
        $this->relation = $relation;
        $this->relationAttr = $attr;

        return $this;
    }

    /** Unique within the current company (or globally). */
    public function unique(string $table, bool $global = false): static
    {
        $this->uniqueTable = $table;
        $this->global = $global;

        return $this;
    }

    public function getLabel(): string
    {
        return $this->label !== null ? __($this->label) : __(Str::headline(Str::beforeLast($this->name, '_id') === $this->name ? $this->name : Str::beforeLast($this->name, '_id')));
    }

    public function getOptions(): array
    {
        $o = $this->options instanceof Closure ? ($this->options)() : ($this->options ?? []);
        if ($o instanceof \Illuminate\Support\Collection) {
            $o = $o->all();
        }
        // list of values => translated labels
        if (array_is_list($o)) {
            $o = array_combine($o, array_map(fn ($v) => status_label((string) $v), $o));
        }

        return $o;
    }

    public function rulesFor(?Model $model): array
    {
        $r = [$this->required ? 'required' : 'nullable'];
        $r = array_merge($r, match ($this->type) {
            'email' => ['email:rfc', 'max:190'],
            'integer' => ['integer', 'min:0', 'max:2147483647'],
            'decimal' => ['numeric', 'max:9999999999'],
            'date', 'datetime' => ['date'],
            'time' => ['date_format:H:i'],
            'checkbox' => ['boolean'],
            'color' => ['string', 'max:20', 'regex:/^#[0-9a-fA-F]{3,8}$/'],
            'image' => ['image', 'mimes:'.config('erp.uploads.image_mimes'), 'max:4096'],
            'tel' => ['string', 'max:30', 'regex:/^[0-9+\-\s()]{6,30}$/'],
            'textarea' => ['string', 'max:10000'],
            'password' => ['string', 'min:8', 'max:190'],
            'select' => [Rule::in(array_map('strval', array_keys($this->getOptions())))],
            'lookup' => ['integer'],
            default => ['string', 'max:255'],
        });
        if ($this->uniqueTable) {
            $rule = Rule::unique($this->uniqueTable, $this->name)->ignore($model?->getKey());
            if (! $this->global && tenant()->id()) {
                $rule->where('company_id', tenant()->id());
            }
            $r[] = $rule;
        }

        return array_merge($r, $this->rules);
    }

    /** Raw value for form inputs. */
    public function value(?Model $m): mixed
    {
        $v = $m?->getAttribute($this->name) ?? $this->default;
        if ($v instanceof \DateTimeInterface) {
            return $this->type === 'datetime' ? $v->format('Y-m-d\TH:i') : $v->format('Y-m-d');
        }

        return $v;
    }

    /** Display text for the lookup input. */
    public function lookupText(?Model $m): string
    {
        if (! $m || ! $this->relation) {
            return '';
        }
        $rel = $m->{$this->relation};
        if (! $rel) {
            return '';
        }
        $a = $this->relationAttr ?? 'name';

        return (string) (method_exists($rel, $a) ? $rel->{$a}() : $rel->{$a});
    }

    /** Safe HTML for tables / detail views. */
    public function render(Model $m): string
    {
        if ($this->displayUsing) {
            return (string) ($this->displayUsing)($m);
        }
        if ($this->relation) {
            return e($this->lookupText($m));
        }
        $v = $m->getAttribute($this->name);
        if ($v === null || $v === '') {
            return '<span class="text-body-tertiary">—</span>';
        }

        return match (true) {
            $this->name === 'status' => status_badge((string) $v),
            $this->type === 'checkbox' => $v ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-x-circle text-body-tertiary"></i>',
            $this->type === 'select' => e($this->getOptions()[(string) $v] ?? $v),
            $this->money => '<span class="font-monospace">'.e(money($v)).'</span>',
            $this->type === 'decimal' => '<span class="font-monospace">'.e(qty($v)).'</span>',
            $this->type === 'date' => e($v instanceof \DateTimeInterface ? $v->format('Y-m-d') : $v),
            $this->type === 'datetime' => e($v instanceof \DateTimeInterface ? $v->format('Y-m-d H:i') : $v),
            $this->type === 'color' => '<span class="d-inline-block rounded border" style="width:18px;height:18px;background:'.e($v).'"></span>',
            $this->type === 'image' => '<img src="'.e(\Illuminate\Support\Facades\Storage::disk('public')->url($v)).'" class="rounded" style="max-height:48px">',
            $this->type === 'password' => '••••••',
            // phone numbers, emails and URLs always read left-to-right (no "96899112233+" in Arabic pages)
            in_array($this->type, ['tel', 'email', 'url'], true) => '<bdi dir="ltr">'.e((string) $v).'</bdi>',
            default => e(Str::limit((string) $v, 120)),
        };
    }

    /** Plain text for exports. */
    public function plain(Model $m): string
    {
        if ($this->displayUsing) {
            return trim(html_entity_decode(strip_tags((string) ($this->displayUsing)($m))));
        }
        if ($this->relation) {
            return $this->lookupText($m);
        }
        $v = $m->getAttribute($this->name);

        return match (true) {
            $v === null => '',
            $this->name === 'status' => status_label((string) $v),
            $this->type === 'checkbox' => $v ? __('Yes') : __('No'),
            $this->type === 'select' => (string) ($this->getOptions()[(string) $v] ?? $v),
            $v instanceof \DateTimeInterface => $this->type === 'datetime' ? $v->format('Y-m-d H:i') : $v->format('Y-m-d'),
            default => (string) $v,
        };
    }
}
