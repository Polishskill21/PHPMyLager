@extends('layouts.app')

@section('title', 'Suppliers – PhpMyLager')
@section('page-title', 'Suppliers')

@push('meta')
    <meta name="can-write" content="{{ Auth::user()->canWrite() ? 'true' : 'false' }}">
    <meta name="can-delete" content="{{ Auth::user()->canDelete() ? 'true' : 'false' }}">
@endpush

@push('styles')
    @vite('resources/css/pages/suppliers.css')
@endpush

@section('content')
@php
    $canWrite = Auth::user()->canWrite();
    $canDelete = Auth::user()->canDelete();
    $showActions = $canWrite || $canDelete;
@endphp

<section class="list-page suppliers-page">
    <header class="page-header">
        <h1 class="page-title">
            <img class="page-title-icon" src="{{ asset('icons/lucide/truck.png') }}" alt="">
            <span>Suppliers</span>
        </h1>
        <p class="page-subtitle">Vendor profiles used by inbound purchase orders.</p>
    </header>

    <div class="page-toolbar suppliers-toolbar">
        <div class="page-search suppliers-search">
            <img class="page-search-icon" src="{{ asset('icons/lucide/search.png') }}" alt="">
            <input class="form-input page-search-input" id="suppliers-search" type="text" placeholder="Search by ID, name, email, phone or city...">
        </div>
        <div class="stat-pill">Suppliers: <span id="suppliers-stat-total">{{ $suppliers->count() }}</span></div>
        <div class="page-toolbar-spacer"></div>
        @if($canWrite)
            <button class="btn btn-primary" id="btn-add-supplier">
                <img class="ui-icon" src="{{ asset('icons/lucide/plus.png') }}" alt="">
                <span>Add Supplier</span>
            </button>
        @endif
    </div>

    <div class="table-shell">
        <div class="table-wrap">
            <table class="data-table suppliers-table" data-static-sort>
                <colgroup>
                    <col class="col-id">
                    <col class="col-name">
                    <col class="col-email">
                    <col class="col-phone">
                    <col class="col-city">
                    <col class="col-pos">
                    @if($showActions)
                        <col class="col-actions">
                    @endif
                </colgroup>
                <thead>
                <tr>
                    <th class="th cell-id" data-sort="id" data-sort-type="number"><span class="table-th-inner"><span>ID</span><span class="sort-arrow" aria-hidden="true">↕</span></span></th>
                    <th class="th cell-name" data-sort="name"><span class="table-th-inner"><span>Name</span><span class="sort-arrow" aria-hidden="true">↕</span></span></th>
                    <th class="th cell-left" data-sort="email"><span class="table-th-inner"><span>Email</span><span class="sort-arrow" aria-hidden="true">↕</span></span></th>
                    <th class="th cell-left" data-sort="phone"><span class="table-th-inner"><span>Phone</span><span class="sort-arrow" aria-hidden="true">↕</span></span></th>
                    <th class="th cell-left" data-sort="city"><span class="table-th-inner"><span>City</span><span class="sort-arrow" aria-hidden="true">↕</span></span></th>
                    <th class="th cell-number" data-sort="pos" data-sort-type="number"><span class="table-th-inner"><span>POs</span><span class="sort-arrow" aria-hidden="true">↕</span></span></th>
                    @if($showActions)
                        <th class="cell-actions">Actions</th>
                    @endif
                </tr>
                </thead>
                <tbody>
                @forelse($suppliers as $supplier)
                    <tr data-sort-row
                        data-sort-id="{{ $supplier->pLiefNr }}"
                        data-sort-name="{{ $supplier->name ?: '' }}"
                        data-sort-email="{{ $supplier->email ?: '' }}"
                        data-sort-phone="{{ $supplier->telefon ?: '' }}"
                        data-sort-city="{{ $supplier->ort ?: '' }}"
                        data-sort-pos="{{ $supplier->purchase_orders_count }}">
                        <td class="cell-id">#{{ $supplier->pLiefNr }}</td>
                        <td class="cell-name" title="{{ $supplier->name }}">{{ $supplier->name ?: '—' }}</td>
                        <td class="cell-muted" title="{{ $supplier->email }}">{{ $supplier->email ?: '—' }}</td>
                        <td title="{{ $supplier->telefon }}">{{ $supplier->telefon ?: '—' }}</td>
                        <td title="{{ $supplier->ort }}">{{ $supplier->ort ?: '—' }}</td>
                        <td class="cell-number suppliers-count">{{ $supplier->purchase_orders_count }}</td>
                        @if($showActions)
                            <td class="cell-actions">
                                <div class="table-actions">
                                    @if($canWrite)
                                        <button class="btn-icon supplier-edit" title="Edit" data-id="{{ $supplier->pLiefNr }}">
                                            <img class="action-icon" src="{{ asset('icons/lucide/pencil.png') }}" alt="Edit">
                                        </button>
                                    @endif
                                    @if($canDelete)
                                        <button class="btn-icon del supplier-delete" title="Delete" data-id="{{ $supplier->pLiefNr }}" data-name="{{ $supplier->name }}">
                                            <img class="action-icon" src="{{ asset('icons/lucide/trash-2.png') }}" alt="Delete">
                                        </button>
                                    @endif
                                </div>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr class="table-state-row">
                        <td class="table-state-cell" colspan="{{ $showActions ? 7 : 6 }}">
                            <div class="empty-state">No suppliers found.</div>
                        </td>
                    </tr>
                @endforelse
                <tr class="table-state-row" id="suppliers-empty-filter-row" hidden>
                    <td class="table-state-cell" colspan="{{ $showActions ? 7 : 6 }}">
                        <div class="empty-state">No suppliers match your search.</div>
                    </td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>
</section>

<div id="toast-area"></div>

<div class="overlay" id="modal-supplier-form-overlay">
    <div class="modal" id="modal-supplier-form">
        <div class="modal-title">
            <span id="supplier-form-title">New Supplier</span>
            <span class="badge" id="supplier-form-badge">CREATE</span>
        </div>

        <form id="supplier-form" autocomplete="off">
            <input type="hidden" id="f-id">
            <div class="form-grid">
                <div class="form-group form-full">
                    <label class="form-label" for="f-name">Name <em class="required-marker">*</em></label>
                    <input class="form-input" id="f-name" maxlength="100" placeholder="e.g. Remscheid Werkzeuge GmbH" required>
                    <div class="form-error" id="err-name"></div>
                </div>

                <div class="form-group form-full">
                    <label class="form-label" for="f-email">Email</label>
                    <input class="form-input" id="f-email" type="email" maxlength="50" placeholder="orders@example.com">
                    <div class="form-error" id="err-email"></div>
                </div>

                <div class="form-group form-full">
                    <label class="form-label" for="f-strasse">Street</label>
                    <input class="form-input" id="f-strasse" maxlength="50" placeholder="Industrial Road 12">
                    <div class="form-error" id="err-strasse"></div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="f-plz">PLZ</label>
                    <input class="form-input" id="f-plz" maxlength="5" inputmode="numeric" placeholder="42853">
                    <div class="form-error" id="err-plz"></div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="f-ort">City</label>
                    <input class="form-input" id="f-ort" maxlength="50" placeholder="Remscheid">
                    <div class="form-error" id="err-ort"></div>
                </div>

                <div class="form-group form-full">
                    <label class="form-label" for="f-telefon">Phone</label>
                    <input class="form-input" id="f-telefon" maxlength="30" placeholder="+49 2191 555120">
                    <div class="form-error" id="err-telefon"></div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-cancel" id="supplier-form-cancel">Cancel</button>
                <button type="submit" class="btn btn-primary btn-submit btn-submit-save" id="supplier-form-submit">Save Supplier</button>
            </div>
        </form>
    </div>
</div>

<div class="overlay" id="modal-supplier-del-overlay">
    <div class="modal modal-sm">
        <div class="modal-title modal-title-danger">Delete Supplier</div>
        <p class="confirm-body">
            This will delete <span class="confirm-target" id="supplier-del-target-name"></span>
            (ID&nbsp;<span class="confirm-target" id="supplier-del-target-id"></span>) if it is not attached to purchase orders.
        </p>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-cancel" id="supplier-del-cancel">Keep it</button>
            <button type="button" class="btn btn-danger btn-submit btn-submit-delete" id="supplier-del-confirm">Delete</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
    <script src="{{ asset('js/list-sort.js') }}?v={{ filemtime(public_path('js/list-sort.js')) }}"></script>
    <script src="{{ asset('js/suppliers.js') }}?v={{ filemtime(public_path('js/suppliers.js')) }}"></script>
@endpush
