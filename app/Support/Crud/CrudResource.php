<?php

namespace App\Support\Crud;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Base class for declarative resources (list / create / edit / show / delete /
 * export) with permission checks "{permission}.{action}" and module gating.
 */
abstract class CrudResource
{
    /** @var class-string<Model> */
    public static string $model;
    public static string $slug;
    public static string $permission;
    public static ?string $module = null;
    public static string $icon = 'bi-grid';
    public static array $with = [];
    public static string $defaultSort = 'id';
    public static string $defaultDir = 'desc';
    public static ?string $dateColumn = null;
    public static bool $readonly = false;
    public static bool $hasAttachments = false;

    abstract public function title(): string;

    /** @return Field[] */
    abstract public function fields(): array;

    public function singular(): string
    {
        return $this->title();
    }

    /** @return Field[] */
    public function formFields(?Model $m = null): array
    {
        return array_values(array_filter($this->fields(), fn (Field $f) => $f->onForm));
    }

    /** @return Field[] */
    public function columns(): array
    {
        return array_values(array_filter($this->fields(), fn (Field $f) => $f->inIndex));
    }

    /** @return Field[] */
    public function filters(): array
    {
        return array_values(array_filter($this->fields(), fn (Field $f) => $f->filterable));
    }

    public function searchColumns(): array
    {
        return array_map(fn (Field $f) => $f->name, array_filter($this->fields(), fn (Field $f) => $f->searchable));
    }

    public function query(): Builder
    {
        return (static::$model)::query()->with(static::$with);
    }

    /** Extra query constraints (e.g. only rows of a type). */
    public function scope(Builder $q): Builder
    {
        return $q;
    }

    public function rules(?Model $m): array
    {
        $rules = [];
        foreach ($this->formFields($m) as $f) {
            $rules[$f->name] = $f->rulesFor($m);
        }

        return $rules;
    }

    /** Non-column (virtual) form fields that must not be filled on the model. */
    public function virtualFields(): array
    {
        return [];
    }

    public function fillable(array $data): array
    {
        return array_diff_key($data, array_flip($this->virtualFields()));
    }

    public function prepare(array $data, ?Model $m): array
    {
        return $data;
    }

    /** Called before save (set guarded attributes, enforce limits). */
    public function saving(Model $m, array $data, bool $creating): void {}

    public function saved(Model $m, array $data, bool $created): void {}

    public function can(string $action, ?Model $m = null): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }
        if (static::$readonly && in_array($action, ['create', 'update', 'delete'], true)) {
            return false;
        }

        return $user->can(static::$permission.'.'.$action);
    }

    public function createUrl(): ?string
    {
        return route('crud.create', static::$slug);
    }

    public function editUrl(Model $m): ?string
    {
        return route('crud.edit', [static::$slug, $m->getKey()]);
    }

    public function showUrl(Model $m): ?string
    {
        return route('crud.show', [static::$slug, $m->getKey()]);
    }

    public function indexUrl(): string
    {
        return route('crud.index', static::$slug);
    }

    /** @return array<int,array{label:string,url:string,icon?:string,method?:string,confirm?:string,class?:string}> */
    public function rowActions(Model $m): array
    {
        return [];
    }

    /** @return array<int,array{label:string,url:string,icon?:string,class?:string}> */
    public function headerActions(): array
    {
        return [];
    }

    /** Return a reason string to block deletion. */
    public function deleteBlocker(Model $m): ?string
    {
        return null;
    }

    public function canEditRecord(Model $m): bool
    {
        return true;
    }

    /** Blade view for the list page (custom designs reuse crud._filters / crud._pager). */
    public function indexView(): string
    {
        return 'crud.index';
    }

    /** Optional Blade view included at the end of the create / edit form (inside the <form>). */
    public function formExtras(?Model $m = null): ?string
    {
        return null;
    }

    /** Optional extra HTML rendered on the show page (Blade view name + data). */
    public function showPanels(Model $m): array
    {
        return [];
    }
    /** Human name of a record for notifications and confirmations: number, name, code… or #id. */
    public function recordLabel(Model $m): string
    {
        $attributes = $m->getAttributes(); // stored columns only (some models have methods named like title())
        foreach (['number', 'name', 'name_ar', 'name_en', 'title', 'code', 'sku', 'plate_number', 'email', 'subject'] as $attr) {
            $v = $attributes[$attr] ?? null;
            if (is_scalar($v) && trim((string) $v) !== '') {
                return \Illuminate\Support\Str::limit((string) $v, 60);
            }
        }

        return '#'.$m->getKey();
    }
}
