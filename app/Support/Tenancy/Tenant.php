<?php

namespace App\Support\Tenancy;

use App\Modules\Core\Models\Company;

/**
 * Holds the current tenant (company) and branch context for the request,
 * console command or queued job. All tenant models are scoped through it.
 */
class Tenant
{
    protected ?int $companyId = null;
    protected ?int $branchId = null;
    /** @var int[]|null null = all branches allowed */
    protected ?array $branchIds = null;
    protected ?Company $company = null;

    public function set(?int $companyId, ?int $branchId = null, ?array $branchIds = null): void
    {
        if ($companyId !== $this->companyId) {
            $this->company = null;
        }
        $this->companyId = $companyId;
        $this->branchId = $branchId;
        $this->branchIds = $branchIds;

        app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($companyId);
    }

    public function id(): ?int
    {
        return $this->companyId;
    }

    public function check(): bool
    {
        return $this->companyId !== null;
    }

    public function company(): ?Company
    {
        if ($this->companyId && (! $this->company || $this->company->id !== $this->companyId)) {
            $this->company = Company::query()->find($this->companyId);
        }

        return $this->company;
    }

    public function branchId(): ?int
    {
        return $this->branchId;
    }

    public function setBranch(?int $branchId): void
    {
        $this->branchId = $branchId;
    }

    public function branchIds(): ?array
    {
        return $this->branchIds;
    }

    public function forget(): void
    {
        $this->set(null);
    }

    /** Run a callback inside another tenant context and restore afterwards. */
    public function run(int $companyId, callable $callback, ?int $branchId = null): mixed
    {
        $prev = [$this->companyId, $this->branchId, $this->branchIds];
        $this->set($companyId, $branchId, null);
        try {
            return $callback();
        } finally {
            $this->set(...$prev);
        }
    }
}
