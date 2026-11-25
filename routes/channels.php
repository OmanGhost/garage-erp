<?php

use Illuminate\Support\Facades\Broadcast;

// per-user notifications
Broadcast::channel('App.Models.User.{id}', fn ($user, $id) => (int) $user->id === (int) $id);

// company-wide operational events (Kanban, dashboards) - staff of that company only
Broadcast::channel('company.{companyId}', fn ($user, $companyId) => ! $user->isCustomer()
    && ((int) $user->company_id === (int) $companyId || ($user->is_super_admin && (int) session('admin_company_id') === (int) $companyId)));
