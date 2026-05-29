@extends('layouts.app')

@section('title', 'Orders - PhpMyLager')
@section('page-title', 'Orders')

@push('meta')
    <meta name="can-write" content="{{ Auth::user()->canWrite() ? 'true' : 'false' }}">
    <meta name="can-delete" content="{{ Auth::user()->canDelete() ? 'true' : 'false' }}">
@endpush

@push('styles')
    @vite('resources/css/pages/orders.css')
@endpush

@section('content')
<section class="orders-page">
    <header class="page-header orders-header">
        <h1 class="page-title">
            <img class="page-title-icon" src="{{ asset('icons/lucide/shopping-cart.png') }}" alt="">
            <span>Orders</span>
        </h1>
        <p class="page-subtitle">Manage and view all registered customer orders.</p>
    </header>

    <div class="page-toolbar orders-toolbar">
        <div class="page-search orders-search">
            <img class="page-search-icon" src="{{ asset('icons/lucide/search.png') }}" alt="">
            <input id="search" class="form-input page-search-input" type="text" placeholder="Search by order or customer ID...">
        </div>

        <div class="stat-pill">Total Orders: <span id="stat-total">0</span></div>
        <div class="stat-pill">Total EUR: <span id="stat-total-eur">0.00</span></div>

        <div class="page-toolbar-spacer"></div>

        @if(Auth::user()->canWrite())
            <button id="btn-add" class="btn btn-primary">
                <img class="ui-icon" src="{{ asset('icons/lucide/plus.png') }}" alt="">
                <span>Add Order</span>
            </button>
        @endif
    </div>

    <div class="table-shell orders-table-shell">
        <div class="table-wrap orders-table-wrap">
            <table class="data-table orders-table" id="orders-table">
                <colgroup>
                    <col class="col-id">
                    <col class="col-customer">
                    <col class="col-created">
                    <col class="col-delivery">
                    <col class="col-items">
                    <col class="col-total">
                    <col class="col-actions">
                </colgroup>
                <thead>
                <tr>
                    <th class="th cell-id" data-sort="pAufNr"><span class="table-th-inner"><span>ID</span><span class="sort-arrow sort-none" aria-hidden="true"></span></span></th>
                    <th class="th cell-customer" data-sort="customer_text"><span class="table-th-inner"><span>Name</span><span class="sort-arrow sort-none" aria-hidden="true"></span></span></th>
                    <th class="th cell-date" data-sort="aufDat"><span class="table-th-inner"><span>Created</span><span class="sort-arrow sort-none" aria-hidden="true"></span></span></th>
                    <th class="th cell-date" data-sort="aufTermin"><span class="table-th-inner"><span>Delivery</span><span class="sort-arrow sort-none" aria-hidden="true"></span></span></th>
                    <th class="th td-items cell-number" data-sort="item_count"><span class="table-th-inner"><span>Items</span><span class="sort-arrow sort-none" aria-hidden="true"></span></span></th>
                    <th class="th td-total cell-money" data-sort="preis_total"><span class="table-th-inner"><span>Total EUR</span><span class="sort-arrow sort-none" aria-hidden="true"></span></span></th>
                    <th class="cell-actions">Actions</th>
                </tr>
                </thead>
                <tbody id="orders-body">
                <tr class="table-state-row">
                    <td class="table-state-cell" colspan="7">
                        <div class="loading-row"><div class="spinner"></div> Loading orders...</div>
                    </td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>
</section>

<div id="toast-area"></div>

<div class="overlay" id="modal-form-overlay">
    <div class="modal modal-large-crud" id="modal-form">
        <div class="modal-title">
            <span id="modal-form-title">New Order</span>
            <span class="badge" id="modal-form-badge">CREATE</span>
        </div>

        <form id="order-form" autocomplete="off">
            <input type="hidden" id="f-id">

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label" for="f-fKdNr">Customer <em class="required-marker">*</em></label>
                    <div class="select-wrap">
                        <select class="form-select" id="f-fKdNr"></select>
                        <span class="modal-control-icon icon-chevron-down" aria-hidden="true"></span>
                    </div>
                    <div class="form-error" id="err-fKdNr"></div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="f-aufDat">Order Date <em class="required-marker">*</em></label>
                    <div class="date-input-wrap">
                        <input class="form-input date-field" id="f-aufDat" type="text" inputmode="none" readonly aria-haspopup="dialog">
                        <span class="modal-control-icon icon-calendar" aria-hidden="true"></span>
                    </div>
                    <div class="form-error" id="err-aufDat"></div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="f-aufTermin">Delivery Date <em class="required-marker">*</em></label>
                    <div class="date-input-wrap">
                        <input class="form-input date-field" id="f-aufTermin" type="text" inputmode="none" readonly aria-haspopup="dialog">
                        <span class="modal-control-icon icon-calendar" aria-hidden="true"></span>
                    </div>
                    <div class="form-error" id="err-aufTermin"></div>
                </div>

                <div class="orders-items-wrap">
                    <div class="orders-items-head">
                        <button type="button" class="btn btn-primary" id="btn-add-item">
                            <span class="modal-control-icon icon-plus" aria-hidden="true"></span>
                            <span>Add Item</span>
                        </button>
                    </div>

                    <div class="orders-items-list" id="item-rows"></div>
                    <div class="form-error" id="err-items"></div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-cancel" id="modal-form-cancel">Cancel</button>
                <button type="submit" class="btn btn-primary btn-submit btn-submit-save" id="modal-form-submit">Save Order</button>
            </div>
        </form>
    </div>
</div>

<div class="overlay" id="modal-view-overlay">
    <div class="modal" id="modal-view">
        <div class="inspect-header">
            <div class="inspect-heading">
                <h2 class="inspect-title">Order Details</h2>
                <span class="badge inspect-badge" id="view-order-badge">#-</span>
            </div>
        </div>

        <p class="inspect-subtitle">Detailed item overview for this customer order.</p>

        <div class="inspect-meta">
            <div class="inspect-box">
                <div class="inspect-label">Customer</div>
                <div class="inspect-value" id="view-customer">-</div>
            </div>
            <div class="inspect-box">
                <div class="inspect-label">Created</div>
                <div class="inspect-value" id="view-created">-</div>
            </div>
            <div class="inspect-box">
                <div class="inspect-label">Delivery</div>
                <div class="inspect-value" id="view-due">-</div>
            </div>
        </div>

        <div class="inspect-items">
            <div class="inspect-item-head">
                <div>Pos</div>
                <div>Product ID</div>
                <div>Product</div>
                <div>Qty</div>
                <div>Unit EUR</div>
                <div>Total EUR</div>
            </div>
            <div class="inspect-item-list" id="view-items"></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-cancel" id="modal-view-close">Close</button>
        </div>
    </div>
</div>

<div class="overlay" id="modal-del-overlay">
    <div class="modal modal-sm">
        <div class="delete-modal-content">
            <div class="delete-modal-copy">
                <div class="modal-title">Delete Order</div>
                <p class="confirm-body">
                    This will delete order <span class="confirm-target" id="del-target-id"></span>
                    and restore stock from its items.
                </p>
            </div>
            <img class="delete-modal-illustration" src="{{ asset('images/delete-order-illustration.png') }}" alt="" aria-hidden="true">
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-cancel" id="modal-del-cancel">Keep it</button>
            <button type="button" class="btn btn-danger btn-submit btn-submit-delete" id="modal-del-confirm">Delete</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
    <script src="{{ asset('js/orders.js') }}?v={{ filemtime(public_path('js/orders.js')) }}"></script>
@endpush
