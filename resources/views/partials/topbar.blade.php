@php
    $branches = $user && $company ? \App\Modules\Core\Models\Branch::query()->when(tenant()->branchIds() !== null, fn($q) => $q->whereIn('id', tenant()->branchIds() ?: [0]))->where('is_active', true)->get() : collect();
    $currentBranch = $branches->firstWhere('id', tenant()->branchId());
@endphp
<header class="app-topbar d-print-none">
    <button class="btn btn-icon d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#appMenu" aria-controls="appMenu" aria-label="{{ __('Toggle menu') }}"><i class="bi bi-list fs-5"></i></button>
    <a href="{{ route('dashboard') }}" class="topbar-brand">
        @if($company?->logoUrl())
            <img src="{{ $company->logoUrl() }}" alt="" class="brand-logo">
        @else
            <span class="brand-mark"><i class="bi bi-car-front-fill"></i></span>
        @endif
        <span class="brand-text text-truncate d-none d-sm-inline">{{ $company?->displayName() ?? config('app.name') }}</span>
    </a>

    <div class="flex-grow-1 mx-2" style="max-width:560px">
        <livewire:global-search />
    </div>

    <div class="d-flex align-items-center gap-1 ms-auto">
        @if($branches->count() > 1)
            <div class="dropdown d-none d-md-block">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-geo-alt"></i> {{ $currentBranch?->displayName() ?? __('All branches') }}</button>
                <ul class="dropdown-menu dropdown-menu-end">
                    @foreach($branches as $b)
                        <li><form method="post" action="{{ route('branch.switch') }}">@csrf<input type="hidden" name="branch_id" value="{{ $b->id }}"><button class="dropdown-item {{ $b->id === tenant()->branchId() ? 'active' : '' }}">{{ $b->displayName() }}</button></form></li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="dropdown">
            <button class="btn btn-icon" data-bs-toggle="dropdown" title="{{ __('Quick actions') }}"><i class="bi bi-lightning-charge fs-5"></i></button>
            <ul class="dropdown-menu dropdown-menu-end shadow">
                <li><h6 class="dropdown-header">{{ __('Quick actions') }}</h6></li>
                @can('reception.create')<li><a class="dropdown-item" href="{{ route('reception.create') }}"><i class="bi bi-box-arrow-in-down me-2"></i>{{ __('Receive vehicle') }}</a></li>@endcan
                @can('pos.view')<li><a class="dropdown-item" href="{{ route('pos.index') }}"><i class="bi bi-upc-scan me-2"></i>{{ __('New POS sale') }}</a></li>@endcan
                @can('invoices.create')<li><a class="dropdown-item" href="{{ route('invoices.create') }}"><i class="bi bi-receipt me-2"></i>{{ __('New invoice') }}</a></li>@endcan
                @can('customers.create')<li><a class="dropdown-item" href="{{ route('crud.create', 'customers') }}"><i class="bi bi-person-plus me-2"></i>{{ __('New customer') }}</a></li>@endcan
                @can('appointments.create')<li><a class="dropdown-item" href="{{ route('appointments.calendar') }}"><i class="bi bi-calendar-plus me-2"></i>{{ __('Book appointment') }}</a></li>@endcan
                @can('payments.create')<li><a class="dropdown-item" href="{{ route('payments.receive') }}"><i class="bi bi-wallet2 me-2"></i>{{ __('Receive payment') }}</a></li>@endcan
                @can('purchase_orders.create')<li><a class="dropdown-item" href="{{ route('purchase-orders.create') }}"><i class="bi bi-cart-plus me-2"></i>{{ __('New purchase order') }}</a></li>@endcan
            </ul>
        </div>

        <livewire:notifications-bell />

        <button class="btn btn-icon" data-erp-theme title="{{ __('Dark / light mode') }}"><i class="bi bi-moon-stars fs-5 theme-icon-dark"></i><i class="bi bi-sun fs-5 theme-icon-light"></i></button>

        <form method="post" action="{{ route('locale.switch') }}" class="d-inline">@csrf
            <input type="hidden" name="locale" value="{{ app()->getLocale() === 'ar' ? 'en' : 'ar' }}">
            <button class="btn btn-icon fw-semibold" title="{{ __('Language') }}">{{ app()->getLocale() === 'ar' ? 'EN' : 'ع' }}</button>
        </form>

        <div class="dropdown">
            <button class="btn btn-sm d-flex align-items-center gap-2" data-bs-toggle="dropdown">
                <span class="avatar">{{ mb_substr($user->name, 0, 1) }}</span>
                <span class="d-none d-md-inline text-truncate" style="max-width:140px">{{ $user->name }}</span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow">
                <li><span class="dropdown-item-text small text-body-secondary">{{ $user->email }}</span></li>
                <li><a class="dropdown-item" href="{{ route('profile.edit') }}"><i class="bi bi-person-circle me-2"></i>{{ __('My profile & security') }}</a></li>
                @if($user->is_super_admin)<li><a class="dropdown-item" href="{{ route('admin.dashboard') }}"><i class="bi bi-buildings me-2"></i>{{ __('SaaS administration') }}</a></li>@endif
                <li><hr class="dropdown-divider"></li>
                <li><form method="post" action="{{ route('logout') }}">@csrf<button class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>{{ __('Logout') }}</button></form></li>
            </ul>
        </div>
    </div>
</header>
