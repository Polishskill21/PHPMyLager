@extends('layouts.app')

@section('title', 'Product Groups – PhpMyLager')
@section('page-title', 'Product Groups')

@push('meta')
    <meta name="can-write" content="{{ Auth::user()->canWrite() ? 'true' : 'false' }}">
    <meta name="can-delete" content="{{ Auth::user()->canDelete() ? 'true' : 'false' }}">
@endpush

@push('styles')
    @vite('resources/css/pages/warehouse.css')
@endpush

@section('content')
@php
    $canWrite = Auth::user()->canWrite();
    $showActions = $canWrite;
    $colspan = $showActions ? 3 : 2;
@endphp

<section class="list-page warehouse-page">
    <header class="page-header">
        <h1 class="page-title">
            <img class="page-title-icon" src="{{ asset('icons/lucide/boxes.png') }}" alt="">
            <span>Product Groups</span>
        </h1>
        <p class="page-subtitle">Categories used to organise products in the warehouse.</p>
    </header>

    <div class="page-toolbar warehouse-toolbar">
        <div class="page-search warehouse-search">
            <img class="page-search-icon" src="{{ asset('icons/lucide/search.png') }}" alt="">
            <input class="form-input page-search-input" id="warehouse-search" type="text" placeholder="Search by ID or name...">
        </div>
        <div class="stat-pill">Groups: <span id="warehouse-stat-total">{{ $groups->count() }}</span></div>
        <div class="page-toolbar-spacer"></div>
        @if($canWrite)
            <button class="btn btn-primary" id="btn-add-group">
                <img class="ui-icon" src="{{ asset('icons/lucide/plus.png') }}" alt="">
                <span>Add Product Group</span>
            </button>
        @endif
    </div>

    <div class="table-shell">
        <div class="table-wrap">
            <table class="data-table warehouse-table" data-static-sort>
                <colgroup>
                    <col class="col-id">
                    <col class="col-name">
                    @if($showActions)
                        <col class="col-actions">
                    @endif
                </colgroup>
                <thead>
                <tr>
                    <th class="th cell-id" data-sort="id" data-sort-type="number"><span class="table-th-inner"><span>ID</span><span class="sort-arrow" aria-hidden="true">↕</span></span></th>
                    <th class="th cell-name" data-sort="name"><span class="table-th-inner"><span>Product Group</span><span class="sort-arrow" aria-hidden="true">↕</span></span></th>
                    @if($showActions)
                        <th class="cell-actions">Actions</th>
                    @endif
                </tr>
                </thead>
                <tbody>
                @forelse($groups as $group)
                    <tr class="row-clickable"
                        data-sort-row
                        data-group-id="{{ $group->pWgNr }}"
                        data-group-name="{{ $group->warengruppe ?: ('Group '.$group->pWgNr) }}"
                        data-sort-id="{{ $group->pWgNr }}"
                        data-sort-name="{{ $group->warengruppe ?: '' }}">
                        <td class="cell-id">#{{ $group->pWgNr }}</td>
                        <td class="cell-name" title="{{ $group->warengruppe }}">{{ $group->warengruppe ?: '—' }}</td>
                        @if($showActions)
                            <td class="cell-actions">
                                <div class="table-actions">
                                    <button class="btn-icon group-edit" title="Edit" data-id="{{ $group->pWgNr }}" data-name="{{ $group->warengruppe }}">
                                        <img class="action-icon" src="{{ asset('icons/lucide/pencil.png') }}" alt="Edit">
                                    </button>
                                </div>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr class="table-state-row">
                        <td class="table-state-cell" colspan="{{ $colspan }}">
                            <div class="empty-state">No product groups found.</div>
                        </td>
                    </tr>
                @endforelse
                <tr class="table-state-row" id="warehouse-empty-filter-row" hidden>
                    <td class="table-state-cell" colspan="{{ $colspan }}">
                        <div class="empty-state">No product groups match your search.</div>
                    </td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>
</section>

<div id="toast-area"></div>

<div class="overlay" id="modal-group-form-overlay">
    <div class="modal" id="modal-group-form">
        <div class="modal-title">
            <span id="group-form-title">New Product Group</span>
            <span class="badge" id="group-form-badge">CREATE</span>
        </div>

        <form id="group-form" autocomplete="off">
            <input type="hidden" id="f-id">
            <div class="form-grid">
                <div class="form-group form-full">
                    <label class="form-label" for="f-warengruppe">Name <em class="required-marker">*</em></label>
                    <input class="form-input" id="f-warengruppe" maxlength="50" placeholder="e.g. Zangen" required>
                    <div class="form-error" id="err-warengruppe"></div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-cancel" id="group-form-cancel">Cancel</button>
                <button type="submit" class="btn btn-primary btn-submit btn-submit-save" id="group-form-submit">Save Product Group</button>
            </div>
        </form>
    </div>
</div>

<div class="overlay" id="modal-group-products-overlay">
    <div class="modal modal-inspect" id="modal-group-products">
        <div class="inspect-header">
            <div class="inspect-heading">
                <h2 class="inspect-title">Product Group Products</h2>
                <span class="badge inspect-badge" id="group-products-badge">#-</span>
            </div>
        </div>
        <p class="inspect-subtitle" id="group-products-subtitle">Products assigned to this group.</p>

        <div class="inspect-items warehouse-group-items">
            <div class="inspect-item-head">
                <div>ID</div>
                <div>Name</div>
            </div>
            <div class="inspect-item-list" id="group-products-list"></div>
        </div>

        <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-cancel" id="group-products-close">Close</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
    <script src="{{ asset('js/list-sort.js') }}?v={{ filemtime(public_path('js/list-sort.js')) }}"></script>
    <script src="{{ asset('js/warehouse.js') }}?v={{ filemtime(public_path('js/warehouse.js')) }}"></script>
@endpush
