@extends('layouts.app')

@section('title', 'Products – PhpMyLager')
@section('page-title', 'Products')

@push('meta')
    <meta name="can-write" content="{{ Auth::user()->canWrite() ? 'true' : 'false' }}">
    <meta name="can-delete" content="{{ Auth::user()->canDelete() ? 'true' : 'false' }}">
@endpush

@push('styles')
    @vite('resources/css/pages/products.css')
@endpush

@section('content')
@php
    $canWrite = Auth::user()->canWrite();
    $canDelete = Auth::user()->canDelete();
@endphp
<section class="list-page products-page">
    <header class="page-header">
        <h1 class="page-title">
            <img class="page-title-icon" src="{{ asset('icons/lucide/drill.png') }}" alt="">
            <span>Products</span>
        </h1>
        <p class="page-subtitle">Manage product inventory, stock levels, and pricing.</p>
    </header>

    <div class="page-toolbar products-toolbar">
        <div class="page-search products-search">
            <img class="page-search-icon" src="{{ asset('icons/lucide/search.png') }}" alt="">
            <input id="search" class="form-input page-search-input" type="text" placeholder="Search by name or ID…" data-list-search>
        </div>

        <div class="select-wrap filter-select">
            <select id="filter-stock" class="form-select" data-list-filter="stock">
                <option value="all">All stock</option>
                <option value="ok">In stock</option>
                <option value="warn">Low stock</option>
                <option value="empty">Out of stock</option>
            </select>
            <span class="modal-control-icon icon-chevron-down" aria-hidden="true"></span>
        </div>

        <div class="select-wrap filter-select">
            <select id="filter-wg" class="form-select" data-list-filter="wg">
                <option value="all">All groups</option>
                @foreach($groups as $group)
                    <option value="{{ $group->pWgNr }}">{{ $group->warengruppe ?: ('Group '.$group->pWgNr) }}</option>
                @endforeach
            </select>
            <span class="modal-control-icon icon-chevron-down" aria-hidden="true"></span>
        </div>

        <div class="stat-pill">Total: <span id="list-total">{{ $meta['total'] }}</span></div>
        <div class="stat-pill">Low: <span id="stat-low" class="stat-low-value">{{ $lowCount }}</span></div>

        <div class="page-toolbar-spacer"></div>

        @if($canWrite)
            <button class="btn btn-primary" id="btn-add">
                <img class="ui-icon" src="{{ asset('icons/lucide/plus.png') }}" alt="">
                <span>Add Product</span>
            </button>
        @endif
    </div>

    <div class="table-shell">
        <div class="table-wrap">
            <table class="data-table products-table" id="products-table"
                   data-list-endpoint="/products/page"
                   data-list-per-page="{{ $meta['perPage'] }}"
                   data-list-page="{{ $meta['page'] }}"
                   data-list-has-more="{{ $meta['hasMore'] ? '1' : '0' }}"
                   data-list-total="{{ $meta['total'] }}"
                   data-list-empty="No products match your filters.">
                <colgroup>
                    <col class="col-id">
                    <col class="col-name">
                    <col class="col-group">
                    <col class="col-buy">
                    <col class="col-sell">
                    <col class="col-stock">
                    <col class="col-reorder">
                    <col class="col-location">
                    <col class="col-actions">
                </colgroup>
                <thead>
                <tr>
                    <th class="th cell-id" data-sort="id" data-sort-type="number"><span class="table-th-inner"><span>ID</span><span class="sort-arrow" aria-hidden="true">↕</span></span></th>
                    <th class="th cell-name" data-sort="name"><span class="table-th-inner"><span>Name</span><span class="sort-arrow" aria-hidden="true">↕</span></span></th>
                    <th class="th cell-left" data-sort="group"><span class="table-th-inner"><span>Group</span><span class="sort-arrow" aria-hidden="true">↕</span></span></th>
                    <th class="th cell-money" data-sort="buy" data-sort-type="number"><span class="table-th-inner"><span>Buy €</span><span class="sort-arrow" aria-hidden="true">↕</span></span></th>
                    <th class="th cell-money" data-sort="sell" data-sort-type="number"><span class="table-th-inner"><span>Sell €</span><span class="sort-arrow" aria-hidden="true">↕</span></span></th>
                    <th class="th cell-status" data-sort="stock" data-sort-type="number"><span class="table-th-inner"><span>Stock</span><span class="sort-arrow" aria-hidden="true">↕</span></span></th>
                    <th class="th cell-number" data-sort="reorder" data-sort-type="number"><span class="table-th-inner"><span>Reorder</span><span class="sort-arrow" aria-hidden="true">↕</span></span></th>
                    <th class="th cell-left" data-sort="location"><span class="table-th-inner"><span>Location</span><span class="sort-arrow" aria-hidden="true">↕</span></span></th>
                    <th class="cell-actions">Actions</th>
                </tr>
                </thead>
                <tbody>
                @forelse($firstRows as $row)
                    @include('partials.rows.products-row', ['row' => $row])
                @empty
                    <tr class="table-state-row">
                        <td class="table-state-cell" colspan="9"><div class="empty-state">No products found.</div></td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="list-more">
            <button class="btn btn-secondary" id="btn-load-more" data-list-more @if(!$meta['hasMore']) hidden @endif>Load more</button>
            <span class="list-more-status">Showing <span id="list-shown">{{ count($firstRows) }}</span> of <span id="list-total-status">{{ $meta['total'] }}</span></span>
        </div>
    </div>
</section>

<div id="toast-area"></div>

<div class="overlay" id="modal-form-overlay">
    <div class="modal" id="modal-form">
        <div class="modal-title">
            <span id="modal-form-title">New Product</span>
            <span class="badge" id="modal-form-badge">CREATE</span>
        </div>
        <form id="product-form" autocomplete="off">
            <input type="hidden" id="f-id">
            <div class="form-grid">
                <div class="form-group form-full">
                    <label class="form-label" for="f-bezeichnung">Name <em class="required-marker">*</em></label>
                    <input class="form-input" id="f-bezeichnung" maxlength="35" placeholder="e.g. Gaming Monitor 27">
                    <div class="form-error" id="err-bezeichnung"></div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="f-fWgNr">Warehouse Group <em class="required-marker">*</em></label>
                    <div class="select-wrap">
                        <select class="form-select" id="f-fWgNr">
                            @foreach($groups as $group)
                                <option value="{{ $group->pWgNr }}">{{ $group->warengruppe ?: ('Group '.$group->pWgNr) }}</option>
                            @endforeach
                        </select>
                        <span class="modal-control-icon icon-chevron-down" aria-hidden="true"></span>
                    </div>
                    <div class="form-error" id="err-fWgNr"></div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="f-ekPreis">Buy Price (€) <em class="required-marker">*</em></label>
                    <div class="number-input-wrap">
                        <input class="form-input" id="f-ekPreis" type="number" step="0.01" min="0" placeholder="0.00">
                        <div class="number-stepper-controls">
                            <button type="button" class="number-stepper-button" title="Increase buy price" aria-label="Increase buy price" data-number-step="up">
                                <span class="modal-control-icon icon-chevron-up" aria-hidden="true"></span>
                            </button>
                            <button type="button" class="number-stepper-button" title="Decrease buy price" aria-label="Decrease buy price" data-number-step="down">
                                <span class="modal-control-icon icon-chevron-down" aria-hidden="true"></span>
                            </button>
                        </div>
                    </div>
                    <div class="form-error" id="err-ekPreis"></div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="f-vkPreis">Sell Price (€) <em class="required-marker">*</em></label>
                    <div class="number-input-wrap">
                        <input class="form-input" id="f-vkPreis" type="number" step="0.01" min="0" placeholder="0.00">
                        <div class="number-stepper-controls">
                            <button type="button" class="number-stepper-button" title="Increase sell price" aria-label="Increase sell price" data-number-step="up">
                                <span class="modal-control-icon icon-chevron-up" aria-hidden="true"></span>
                            </button>
                            <button type="button" class="number-stepper-button" title="Decrease sell price" aria-label="Decrease sell price" data-number-step="down">
                                <span class="modal-control-icon icon-chevron-down" aria-hidden="true"></span>
                            </button>
                        </div>
                    </div>
                    <div class="form-error" id="err-vkPreis"></div>
                </div>
                <div class="form-group" id="fg-bestand">
                    <label class="form-label" for="f-bestand">Stock Qty <em class="required-marker">*</em></label>
                    <div class="number-input-wrap">
                        <input class="form-input" id="f-bestand" type="number" min="0" step="1" placeholder="0">
                        <div class="number-stepper-controls">
                            <button type="button" class="number-stepper-button" title="Increase stock" aria-label="Increase stock" data-number-step="up">
                                <span class="modal-control-icon icon-chevron-up" aria-hidden="true"></span>
                            </button>
                            <button type="button" class="number-stepper-button" title="Decrease stock" aria-label="Decrease stock" data-number-step="down">
                                <span class="modal-control-icon icon-chevron-down" aria-hidden="true"></span>
                            </button>
                        </div>
                    </div>
                    <div class="form-error" id="err-bestand"></div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="f-meldeBest">Reorder Level <em class="required-marker">*</em></label>
                    <div class="number-input-wrap">
                        <input class="form-input" id="f-meldeBest" type="number" min="0" step="1" placeholder="0">
                        <div class="number-stepper-controls">
                            <button type="button" class="number-stepper-button" title="Increase reorder level" aria-label="Increase reorder level" data-number-step="up">
                                <span class="modal-control-icon icon-chevron-up" aria-hidden="true"></span>
                            </button>
                            <button type="button" class="number-stepper-button" title="Decrease reorder level" aria-label="Decrease reorder level" data-number-step="down">
                                <span class="modal-control-icon icon-chevron-down" aria-hidden="true"></span>
                            </button>
                        </div>
                    </div>
                    <div class="form-error" id="err-meldeBest"></div>
                </div>
                <div class="form-group form-full">
                    <span class="form-label">Storage Location</span>
                    <div class="lagerplatz-fields" id="lagerplatz-fields">
                        <div class="lagerplatz-part">
                            <div class="select-wrap">
                                <select class="form-select" id="f-lp-zone" aria-label="Zone (A–Z)">
                                    <option value="">–</option>
                                    @foreach(range('A', 'Z') as $z)
                                        <option value="{{ $z }}">{{ $z }}</option>
                                    @endforeach
                                </select>
                                <span class="modal-control-icon icon-chevron-down" aria-hidden="true"></span>
                            </div>
                            <span class="lagerplatz-hint">Zone</span>
                        </div>
                        <div class="lagerplatz-part lagerplatz-num">
                            <input class="form-input" id="f-lp-regal" inputmode="numeric" maxlength="2" placeholder="01" aria-label="Regal (01–99)">
                            <span class="lagerplatz-hint">Regal</span>
                        </div>
                        <span class="lagerplatz-sep" aria-hidden="true">–</span>
                        <div class="lagerplatz-part lagerplatz-num">
                            <input class="form-input" id="f-lp-fach" inputmode="numeric" maxlength="2" placeholder="03" aria-label="Fach (01–99)">
                            <span class="lagerplatz-hint">Fach</span>
                        </div>
                        <div class="lagerplatz-part">
                            <div class="select-wrap">
                                <select class="form-select" id="f-lp-ebene" aria-label="Ebene (A–E)">
                                    <option value="">–</option>
                                    @foreach(range('A', 'E') as $e)
                                        <option value="{{ $e }}">{{ $e }}</option>
                                    @endforeach
                                </select>
                                <span class="modal-control-icon icon-chevron-down" aria-hidden="true"></span>
                            </div>
                            <span class="lagerplatz-hint">Ebene</span>
                        </div>
                    </div>
                    <div class="form-error" id="err-lagerplatz"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-cancel" id="modal-form-cancel">Cancel</button>
                <button type="submit" class="btn btn-primary btn-submit btn-submit-save" id="modal-form-submit">Save Product</button>
            </div>
        </form>
    </div>
</div>

<div class="overlay" id="modal-del-overlay">
    <div class="modal modal-sm">
        <div class="modal-title modal-title-danger">Discontinue Product</div>
        <p class="confirm-body">
            This will soft-delete <span class="confirm-target" id="del-target-name"></span>
            (ID&nbsp;<span class="confirm-target" id="del-target-id"></span>).
            The product will no longer appear in the catalogue but will remain visible on existing orders.
        </p>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-cancel" id="modal-del-cancel">Keep it</button>
            <button type="button" class="btn btn-danger btn-submit btn-submit-delete" id="modal-del-confirm">Discontinue</button>
        </div>
    </div>
</div>

<div class="overlay" id="modal-adjust-overlay">
    <div class="modal" id="modal-adjust">
        <div class="modal-title">
            <span>Adjust Stock</span>
            <span class="badge" id="adjust-badge">#-</span>
        </div>
        <p class="adjust-subtitle">Set a new physical stock level. Every adjustment is recorded with a reason.</p>
        <form id="adjust-form" autocomplete="off">
            <input type="hidden" id="f-adjust-id">
            <div class="form-grid">
                <div class="form-group form-full">
                    <span class="form-label">Product</span>
                    <div class="adjust-readonly" id="adjust-product-name">—</div>
                </div>
                <div class="form-group">
                    <span class="form-label">Current Stock</span>
                    <div class="adjust-readonly" id="adjust-current-stock">—</div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="f-adjust-bestand">New Stock Level <em class="required-marker">*</em></label>
                    <div class="number-input-wrap">
                        <input class="form-input" id="f-adjust-bestand" type="number" min="0" step="1" placeholder="0">
                        <div class="number-stepper-controls">
                            <button type="button" class="number-stepper-button" title="Increase stock" aria-label="Increase stock" data-number-step="up">
                                <span class="modal-control-icon icon-chevron-up" aria-hidden="true"></span>
                            </button>
                            <button type="button" class="number-stepper-button" title="Decrease stock" aria-label="Decrease stock" data-number-step="down">
                                <span class="modal-control-icon icon-chevron-down" aria-hidden="true"></span>
                            </button>
                        </div>
                    </div>
                    <div class="form-error" id="err-adjust-bestand"></div>
                </div>
                <div class="form-group form-full">
                    <label class="form-label" for="f-adjust-reason">Reason <em class="required-marker">*</em></label>
                    <textarea class="form-input adjust-reason" id="f-adjust-reason" rows="3" placeholder="e.g. Stocktake correction, damaged goods, returns"></textarea>
                    <div class="char-counter" id="adjust-reason-count">0 / 255</div>
                    <div class="form-error" id="err-adjust-reason"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-cancel" id="modal-adjust-cancel">Cancel</button>
                <button type="submit" class="btn btn-primary btn-submit btn-submit-save" id="modal-adjust-submit">Save Adjustment</button>
            </div>
        </form>
    </div>
</div>

<div class="overlay" id="modal-history-overlay">
    <div class="modal modal-inspect" id="modal-history">
        <div class="inspect-header">
            <div class="inspect-heading">
                <h2 class="inspect-title">Stock History</h2>
                <span class="badge inspect-badge" id="history-badge">#-</span>
            </div>
        </div>
        <p class="inspect-subtitle">Manual stock adjustments recorded for this product.</p>
        <div class="inspect-items products-history-items">
            <div class="inspect-item-head">
                <div>Date</div>
                <div>User</div>
                <div class="num">Old</div>
                <div class="num">New</div>
                <div class="num">Difference</div>
                <div>Reason</div>
            </div>
            <div class="inspect-item-list" id="history-items"></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-cancel" id="modal-history-close">Close</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
    <script src="{{ asset('js/list-loadmore.js') }}?v={{ filemtime(public_path('js/list-loadmore.js')) }}"></script>
    <script src="{{ asset('js/products.js') }}?v={{ filemtime(public_path('js/products.js')) }}"></script>
@endpush
