@extends('layouts.app')

@section('title', 'Customers – Storage Management System')
@section('page-title', 'Customers')

@push('meta')
    <meta name="can-write" content="{{ Auth::user()->canWrite() ? 'true' : 'false' }}">
    <meta name="can-delete" content="{{ Auth::user()->canDelete() ? 'true' : 'false' }}">
@endpush

@push('styles')
    @vite('resources/css/pages/customers.css')
@endpush

@section('content')
@php
    $canWrite = Auth::user()->canWrite();
    $canDelete = Auth::user()->canDelete();
@endphp
<section class="list-page customers-page">
    <header class="page-header">
        <h1 class="page-title">
            <img class="page-title-icon" src="{{ asset('icons/lucide/users-round.png') }}" alt="">
            <span>Customers</span>
        </h1>
        <p class="page-subtitle">Manage customer records and contact details.</p>
    </header>

    <div class="page-toolbar customers-toolbar">
        <div class="page-search customers-search">
            <img class="page-search-icon" src="{{ asset('icons/lucide/search.png') }}" alt="">
            <input id="search" class="form-input page-search-input" type="text" placeholder="Search by ID, name, email, street, city or PLZ…">
        </div>

        <div class="stat-pill">Customers: <span id="customers-stat-total">{{ $customers->count() }}</span></div>

        <div class="page-toolbar-spacer"></div>

        @if($canWrite)
            <button class="btn btn-primary" id="btn-add">
                <img class="ui-icon" src="{{ asset('icons/lucide/plus.png') }}" alt="">
                <span>Add Customer</span>
            </button>
        @endif
    </div>

    <div class="table-shell">
        <div class="table-wrap">
            <table class="data-table customers-table" id="customers-table" data-static-sort>
                <colgroup>
                    <col class="col-id">
                    <col class="col-name">
                    <col class="col-email">
                    <col class="col-street">
                    <col class="col-city">
                    <col class="col-plz">
                    <col class="col-actions">
                </colgroup>
                <thead>
                <tr>
                    <th class="th cell-id" data-sort="id" data-sort-type="number"><span class="table-th-inner"><span>ID</span><span class="sort-arrow" aria-hidden="true">↕</span></span></th>
                    <th class="th cell-name" data-sort="name"><span class="table-th-inner"><span>Name</span><span class="sort-arrow" aria-hidden="true">↕</span></span></th>
                    <th class="th cell-left" data-sort="email"><span class="table-th-inner"><span>Email</span><span class="sort-arrow" aria-hidden="true">↕</span></span></th>
                    <th class="th cell-left" data-sort="street"><span class="table-th-inner"><span>Street</span><span class="sort-arrow" aria-hidden="true">↕</span></span></th>
                    <th class="th cell-left" data-sort="city"><span class="table-th-inner"><span>City</span><span class="sort-arrow" aria-hidden="true">↕</span></span></th>
                    <th class="th cell-number" data-sort="plz" data-sort-type="number"><span class="table-th-inner"><span>PLZ</span><span class="sort-arrow" aria-hidden="true">↕</span></span></th>
                    <th class="cell-actions">Actions</th>
                </tr>
                </thead>
                <tbody>
                @forelse($customers as $customer)
                    <tr data-sort-row
                        data-sort-id="{{ $customer->pKdNr }}"
                        data-sort-name="{{ $customer->name ?: '' }}"
                        data-sort-email="{{ $customer->email ?: '' }}"
                        data-sort-street="{{ $customer->strasse ?: '' }}"
                        data-sort-city="{{ $customer->ort ?: '' }}"
                        data-sort-plz="{{ $customer->plz ?: '' }}">
                        <td class="cell-id">#{{ $customer->pKdNr }}</td>
                        <td class="cell-name" title="{{ $customer->name }}">{{ $customer->name ?: '—' }}</td>
                        <td class="cell-muted" title="{{ $customer->email }}">{{ $customer->email ?: '—' }}</td>
                        <td title="{{ $customer->strasse }}">{{ $customer->strasse ?: '—' }}</td>
                        <td title="{{ $customer->ort }}">{{ $customer->ort ?: '—' }}</td>
                        <td class="cell-number">{{ $customer->plz ?: '—' }}</td>
                        <td class="cell-actions">
                            <div class="table-actions">
                                @if($canWrite)
                                    <button class="btn-icon customer-edit" title="Edit" data-id="{{ $customer->pKdNr }}">
                                        <img class="action-icon" src="{{ asset('icons/lucide/pencil.png') }}" alt="Edit">
                                    </button>
                                @endif
                                @if($canDelete)
                                    <button class="btn-icon del customer-delete" title="Archive" data-id="{{ $customer->pKdNr }}" data-name="{{ $customer->name }}">
                                        <img class="action-icon" src="{{ asset('icons/lucide/trash-2.png') }}" alt="Archive">
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr class="table-state-row">
                        <td class="table-state-cell" colspan="7"><div class="empty-state">No customers found.</div></td>
                    </tr>
                @endforelse
                <tr class="table-state-row" id="customers-empty-filter-row" hidden>
                    <td class="table-state-cell" colspan="7"><div class="empty-state">No customers match your search.</div></td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>
</section>

<div id="toast-area"></div>

<div class="overlay" id="modal-form-overlay">
    <div class="modal" id="modal-form">
        <div class="modal-title">
            <span id="modal-form-title">New Customer</span>
            <span class="badge" id="modal-form-badge">CREATE</span>
        </div>
        <form id="customer-form" autocomplete="off">
            <input type="hidden" id="f-id">
            <div class="form-grid">
                <div class="form-group form-full">
                    <label class="form-label" for="f-name">Name <em class="required-marker">*</em></label>
                    <input class="form-input" id="f-name" maxlength="255" placeholder="e.g. Max Mustermann GmbH">
                    <div class="form-error" id="err-name"></div>
                </div>

                <div class="form-group form-full">
                    <label class="form-label" for="f-strasse">Street <em class="required-marker">*</em></label>
                    <input class="form-input" id="f-strasse" maxlength="255" placeholder="e.g. Musterstrasse 1">
                    <div class="form-error" id="err-strasse"></div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="f-plz">PLZ <em class="required-marker">*</em></label>
                    <input class="form-input" id="f-plz" maxlength="5" placeholder="80331">
                    <div class="form-error" id="err-plz"></div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="f-ort">City <em class="required-marker">*</em></label>
                    <input class="form-input" id="f-ort" maxlength="255" placeholder="Muenchen">
                    <div class="form-error" id="err-ort"></div>
                </div>

                <div class="form-group form-full">
                    <label class="form-label" for="f-email">Email <em class="required-marker">*</em></label>
                    <input class="form-input" id="f-email" maxlength="255" placeholder="kunde@example.com">
                    <div class="form-error" id="err-email"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-cancel" id="modal-form-cancel">Cancel</button>
                <button type="submit" class="btn btn-primary btn-submit btn-submit-save" id="modal-form-submit">Save Customer</button>
            </div>
        </form>
    </div>
</div>

<div class="overlay" id="modal-del-overlay">
    <div class="modal modal-sm">
        <div class="modal-title modal-title-danger">Archive Customer</div>
        <p class="confirm-body">
            This will soft-delete <span class="confirm-target" id="del-target-name"></span>
            (ID&nbsp;<span class="confirm-target" id="del-target-id"></span>).
            The customer will no longer appear in the list but existing orders stay intact.
        </p>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-cancel" id="modal-del-cancel">Keep it</button>
            <button type="button" class="btn btn-danger btn-submit btn-submit-delete" id="modal-del-confirm">Archive</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
    <script src="{{ asset('js/list-sort.js') }}?v={{ filemtime(public_path('js/list-sort.js')) }}"></script>
    <script src="{{ asset('js/customers.js') }}?v={{ filemtime(public_path('js/customers.js')) }}"></script>
@endpush
