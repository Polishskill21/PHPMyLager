@extends('layouts.app')

@section('title', 'Purchase Orders – PhpMyLager')
@section('page-title', 'Purchase Orders')

@push('meta')
    <meta name="can-write" content="{{ Auth::user()->canWrite() ? 'true' : 'false' }}">
    <meta name="can-delete" content="{{ Auth::user()->canDelete() ? 'true' : 'false' }}">
@endpush

@push('styles')
    @vite('resources/css/pages/purchase-orders.css')
@endpush

@section('content')
@php
    $canWrite = Auth::user()->canWrite();
    $canDelete = Auth::user()->canDelete();
    $showActions = $canWrite || $canDelete;
@endphp

<section class="list-page purchase-orders-page">
    <header class="page-header">
        <h1 class="page-title">
            <img class="page-title-icon" src="{{ asset('icons/lucide/package.png') }}" alt="">
            <span>Purchase Orders</span>
        </h1>
        <p class="page-subtitle">Inbound supplier orders and receiving status.</p>
    </header>

    <div class="page-toolbar purchase-orders-toolbar">
        <div class="page-search purchase-orders-search">
            <img class="page-search-icon" src="{{ asset('icons/lucide/search.png') }}" alt="">
            <input class="form-input page-search-input" id="purchase-orders-search" type="text" placeholder="Search by ID, supplier, status or date...">
        </div>
        <div class="stat-pill">Purchase Orders: <span id="purchase-orders-stat-total">{{ $purchaseOrders->count() }}</span></div>
        <div class="page-toolbar-spacer"></div>
        @if($canWrite)
            <button class="btn btn-primary" id="btn-add-purchase-order">
                <img class="ui-icon" src="{{ asset('icons/lucide/plus.png') }}" alt="">
                <span>Add Purchase Order</span>
            </button>
        @endif
    </div>

    <div class="table-shell">
        <div class="table-wrap">
            <table class="data-table purchase-orders-table" data-static-sort>
                <colgroup>
                    <col class="col-id">
                    <col class="col-supplier">
                    <col class="col-status">
                    <col class="col-ordered">
                    <col class="col-expected">
                    <col class="col-items">
                    <col class="col-value">
                    @if($showActions)
                        <col class="col-actions">
                    @endif
                </colgroup>
                <thead>
                <tr>
                    <th class="th cell-id" data-sort="id" data-sort-type="number"><span class="table-th-inner"><span>ID</span><span class="sort-arrow" aria-hidden="true">↕</span></span></th>
                    <th class="th cell-name" data-sort="supplier"><span class="table-th-inner"><span>Name</span><span class="sort-arrow" aria-hidden="true">↕</span></span></th>
                    <th class="th cell-status" data-sort="status"><span class="table-th-inner"><span>Status</span><span class="sort-arrow" aria-hidden="true">↕</span></span></th>
                    <th class="th cell-date" data-sort="ordered"><span class="table-th-inner"><span>Ordered</span><span class="sort-arrow" aria-hidden="true">↕</span></span></th>
                    <th class="th cell-date" data-sort="expected"><span class="table-th-inner"><span>Expected</span><span class="sort-arrow" aria-hidden="true">↕</span></span></th>
                    <th class="th cell-number" data-sort="items" data-sort-type="number"><span class="table-th-inner"><span>Items</span><span class="sort-arrow" aria-hidden="true">↕</span></span></th>
                    <th class="th cell-money" data-sort="value" data-sort-type="number"><span class="table-th-inner"><span>Value</span><span class="sort-arrow" aria-hidden="true">↕</span></span></th>
                    @if($showActions)
                        <th class="cell-actions">Actions</th>
                    @endif
                </tr>
                </thead>
                <tbody>
                @forelse($purchaseOrders as $order)
                    @php
                        $totalValue = $order->items->sum(fn ($item) => (float) ($item->ekPreis ?? 0) * (int) $item->bestMenge);
                        $status = $order->status ?: 'offen';
                        $isEditable = in_array($status, ['offen', 'bestellt'], true);
                    @endphp
                    <tr data-sort-row
                        data-sort-id="{{ $order->pBestNr }}"
                        data-sort-supplier="{{ $order->supplier?->name ?: '' }}"
                        data-sort-status="{{ $status }}"
                        data-sort-ordered="{{ $order->bestDat ? substr((string) $order->bestDat, 0, 10) : '' }}"
                        data-sort-expected="{{ $order->erwLieferDat ? substr((string) $order->erwLieferDat, 0, 10) : '' }}"
                        data-sort-items="{{ $order->items->count() }}"
                        data-sort-value="{{ $totalValue }}">
                        <td class="cell-id">#{{ $order->pBestNr }}</td>
                        <td class="cell-name" title="{{ $order->supplier?->name }}">{{ $order->supplier?->name ?: '—' }}</td>
                        <td class="cell-status purchase-orders-status"><span class="status-badge status-{{ $status }}">{{ $status }}</span></td>
                        <td class="cell-date purchase-orders-date">{{ $order->bestDat ? substr((string) $order->bestDat, 0, 10) : '—' }}</td>
                        <td class="cell-date purchase-orders-date">{{ $order->erwLieferDat ? substr((string) $order->erwLieferDat, 0, 10) : '—' }}</td>
                        <td class="cell-number">{{ $order->items->count() }}</td>
                        <td class="cell-money">€{{ number_format($totalValue, 2) }}</td>
                        @if($showActions)
                            <td class="cell-actions">
                                <div class="table-actions">
                                    @if($canWrite && $isEditable)
                                        <button class="btn-icon purchase-order-edit" title="Edit" data-id="{{ $order->pBestNr }}">
                                            <img class="action-icon" src="{{ asset('icons/lucide/pencil.png') }}" alt="Edit">
                                        </button>
                                    @endif
                                    @if($canDelete && $isEditable)
                                        <button class="btn-icon del purchase-order-delete" title="Delete" data-id="{{ $order->pBestNr }}">
                                            <img class="action-icon" src="{{ asset('icons/lucide/trash-2.png') }}" alt="Delete">
                                        </button>
                                    @endif
                                </div>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr class="table-state-row">
                        <td class="table-state-cell" colspan="{{ $showActions ? 8 : 7 }}">
                            <div class="empty-state">No purchase orders found.</div>
                        </td>
                    </tr>
                @endforelse
                <tr class="table-state-row" id="purchase-orders-empty-filter-row" hidden>
                    <td class="table-state-cell" colspan="{{ $showActions ? 8 : 7 }}">
                        <div class="empty-state">No purchase orders match your search.</div>
                    </td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>
</section>

<div id="toast-area"></div>

<div class="overlay" id="modal-purchase-order-form-overlay">
    <div class="modal modal-large-crud purchase-order-modal" id="modal-purchase-order-form">
        <div class="modal-title">
            <span id="purchase-order-form-title">New Purchase Order</span>
            <span class="badge" id="purchase-order-form-badge">CREATE</span>
        </div>

        <form id="purchase-order-form" autocomplete="off">
            <input type="hidden" id="f-id">
            <div class="form-grid purchase-order-form-grid">
                <div class="form-group">
                    <label class="form-label" for="f-fLiefNr">Supplier <em class="required-marker">*</em></label>
                    <div class="select-wrap">
                        <select class="form-select" id="f-fLiefNr" required></select>
                        <span class="modal-control-icon icon-chevron-down" aria-hidden="true"></span>
                    </div>
                    <div class="form-error" id="err-fLiefNr"></div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="f-bestDat">Ordered <em class="required-marker">*</em></label>
                    <div class="date-input-wrap">
                        <input class="form-input date-field" id="f-bestDat" type="text" inputmode="none" readonly required aria-haspopup="dialog">
                        <span class="modal-control-icon icon-calendar" aria-hidden="true"></span>
                    </div>
                    <div class="form-error" id="err-bestDat"></div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="f-erwLieferDat">Expected</label>
                    <div class="date-input-wrap">
                        <input class="form-input date-field" id="f-erwLieferDat" type="text" inputmode="none" readonly aria-haspopup="dialog">
                        <span class="modal-control-icon icon-calendar" aria-hidden="true"></span>
                    </div>
                    <div class="form-error" id="err-erwLieferDat"></div>
                </div>

                <div class="purchase-order-items-wrap">
                    <div class="purchase-order-items-head">
                        <button type="button" class="btn btn-primary" id="btn-add-purchase-order-item">
                            <span class="modal-control-icon icon-plus" aria-hidden="true"></span>
                            <span>Add Item</span>
                        </button>
                    </div>

                    <div class="purchase-order-items-list" id="purchase-order-item-rows"></div>
                    <div class="form-error" id="err-items"></div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-cancel" id="purchase-order-form-cancel">Cancel</button>
                <button type="submit" class="btn btn-primary btn-submit btn-submit-save" id="purchase-order-form-submit">Save Purchase Order</button>
            </div>
        </form>
    </div>
</div>

<div class="overlay" id="modal-purchase-order-del-overlay">
    <div class="modal modal-sm">
        <div class="modal-title modal-title-danger">Delete Purchase Order</div>
        <p class="confirm-body">
            This uses the current delete route and cancels purchase order
            <span class="confirm-target" id="purchase-order-del-target-id"></span> when it is still open or ordered.
        </p>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-cancel" id="purchase-order-del-cancel">Keep it</button>
            <button type="button" class="btn btn-danger btn-submit btn-submit-delete" id="purchase-order-del-confirm">Delete</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
    <script src="{{ asset('js/list-sort.js') }}?v={{ filemtime(public_path('js/list-sort.js')) }}"></script>
    <script src="{{ asset('js/purchase-orders.js') }}?v={{ filemtime(public_path('js/purchase-orders.js')) }}"></script>
@endpush
