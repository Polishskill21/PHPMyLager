@extends('layouts.app')

@section('title', 'Orders – PhpMyLager')
@section('page-title', 'Orders')

@push('meta')
    <meta name="can-write" content="{{ Auth::user()->canWrite() ? 'true' : 'false' }}">
    <meta name="can-delete" content="{{ Auth::user()->canDelete() ? 'true' : 'false' }}">
@endpush

@push('styles')
<style>
    /* ── LAYOUT ── */
    .layout { display: flex; height: calc(100vh - 60px); }

    /* ── SIDEBAR ── */
    aside {
        width: 200px; flex-shrink: 0;
        background: var(--surface); border-right: 1px solid var(--border);
        display: flex; flex-direction: column; padding: 1.5rem 0;
    }
    .aside-label { font-size: .65rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: var(--muted); padding: 0 1.25rem; margin-bottom: .5rem; }
    .aside-link { display: flex; align-items: center; gap: .65rem; padding: .55rem 1.25rem; font-size: .85rem; font-weight: 600; color: var(--muted); text-decoration: none; border-left: 2px solid transparent; transition: all .15s; }
    .aside-link:hover { color: var(--text); background: rgba(59, 130, 246, 0.05); }
    .aside-link.active { color: var(--accent); border-left-color: var(--accent); background: rgba(59, 130, 246, 0.08); }
    .aside-icon { font-size: 1rem; width: 18px; text-align: center; }

    /* ── MAIN ── */
    main { flex: 1; display: flex; flex-direction: column; overflow: hidden; padding: 1.5rem; background: var(--bg); }

    /* ── TOOLBAR ── */
    .toolbar { display: flex; align-items: center; gap: .75rem; margin-bottom: 1rem; flex-shrink: 0; }
    .search-wrap { flex: 1; max-width: 420px; position: relative; }
    .search-icon { position: absolute; left: .75rem; top: 50%; transform: translateY(-50%); color: var(--muted); font-size: .9rem; pointer-events: none; }
    .search-input { width: 100%; padding: .55rem .75rem .55rem 2.2rem; background: var(--surface2); border: 1px solid var(--border); border-radius: 6px; color: var(--text); font-family: var(--mono); font-size: .8rem; transition: border-color .2s; }
    .search-input:focus { outline: none; border-color: var(--accent); }
    .spacer { flex: 1; }

    /* ── TABLE HEADER ── */
    .tbl-head { display: grid; grid-template-columns: 70px 220px 150px 150px 80px 80px 110px 110px; gap: 0; padding: 0 .75rem; border: 1px solid var(--border); border-radius: 7px 7px 0 0; background: var(--surface); flex-shrink: 0; }
    .th { padding: .65rem .5rem; font-size: .65rem; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: var(--muted); border-right: 1px solid var(--border); display: flex; align-items: center; gap: .3rem; cursor: pointer; user-select: none; transition: color .15s; }
    .th:hover { color: var(--text); }
    .th:last-child { border-right: none; cursor: default; }
    .th.sorted { color: var(--accent); }
    .sort-arrow { font-size: .6rem; }

    /* ── VIRTUAL SCROLL CONTAINER ── */
    .vscroll-wrap { flex: 1; overflow-y: auto; overflow-x: hidden; border: 1px solid var(--border); border-top: none; border-radius: 0 0 7px 7px; background: var(--surface); position: relative; }
    .vscroll-wrap::-webkit-scrollbar { width: 6px; }
    .vscroll-wrap::-webkit-scrollbar-track { background: transparent; }
    .vscroll-wrap::-webkit-scrollbar-thumb { background: var(--border2); border-radius: 3px; }
    .vscroll-inner { position: relative; }

    /* ── ROW ── */
    .row { position: absolute; left: 0; right: 0; height: var(--row-h); display: grid; grid-template-columns: 70px 220px 150px 150px 80px 80px 110px 110px; border-bottom: 1px solid var(--border); align-items: center; padding: 0 .75rem; transition: background .1s; }
    .row:hover { background: rgba(59, 130, 246, 0.04); }
    .row-clickable { cursor: pointer; }
    .cell { padding: 0 .5rem; font-size: .82rem; font-family: var(--mono); color: var(--text); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; border-right: 1px solid var(--border); }
    .cell:last-child { border-right: none; }
    .cell-id { color: var(--muted); font-size: .75rem; }
    .cell-customer { font-family: var(--sans); font-size: .82rem; }
    .cell-num { text-align: right; }

    .actions { display: flex; gap: .4rem; align-items: center; justify-content: flex-end; }
    .btn-icon { width: 28px; height: 28px; border-radius: 5px; border: 1px solid var(--border); background: transparent; color: var(--muted); font-size: .82rem; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all .15s; }
    .btn-icon:hover.edit  { border-color: var(--accent); color: var(--accent); background: rgba(59, 130, 246, 0.1); }
    .btn-icon:hover.del   { border-color: var(--red);    color: var(--red);    background: rgba(239, 68, 68, 0.1); }

    .empty-state { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 4rem; gap: .75rem; color: var(--muted); }
    .empty-state .icon { font-size: 2.5rem; opacity: .4; }
    .empty-state p { font-size: .9rem; }

    .loading-row { display: flex; align-items: center; justify-content: center; height: 120px; color: var(--muted); font-size: .85rem; gap: .6rem; }
    .spinner { width: 18px; height: 18px; border: 2px solid var(--border2); border-top-color: var(--accent); border-radius: 50%; animation: spin .7s linear infinite; }
    @keyframes spin { to { transform: rotate(360deg); } }

    /* ── ORDER FORM ── */
    .items-wrap { grid-column: 1 / -1; margin-top: .25rem; }
    .items-head {
        display: flex; align-items: center; justify-content: space-between;
        margin-bottom: .45rem;
    }
    .items-label {
        font-size: .68rem; font-weight: 700; letter-spacing: .1em;
        text-transform: uppercase; color: var(--muted);
    }
    .items-list { display: flex; flex-direction: column; gap: .45rem; }
    .item-row {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 120px 32px;
        gap: .45rem;
        align-items: center;
    }
    .item-row .form-select,
    .item-row .form-input {
        width: 100%;
        min-width: 0;
    }
    .item-remove {
        width: 32px; height: 32px; border-radius: 6px; border: 1px solid var(--border);
        background: transparent; color: var(--muted); cursor: pointer; font-size: .95rem;
    }
    .item-remove:hover {
        border-color: var(--red); color: var(--red); background: rgba(239, 68, 68, 0.1);
    }

    /* ── INSPECT MODAL ── */
    #modal-view { max-width: 860px; }
    .inspect-meta {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: .6rem;
        margin-bottom: .9rem;
    }
    .inspect-box {
        border: 1px solid var(--border);
        background: var(--surface2);
        border-radius: 7px;
        padding: .55rem .65rem;
        min-width: 0;
    }
    .inspect-label {
        font-size: .62rem;
        text-transform: uppercase;
        letter-spacing: .08em;
        color: var(--muted);
        margin-bottom: .2rem;
    }
    .inspect-value {
        font-family: var(--mono);
        font-size: .8rem;
        color: var(--text);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .inspect-items {
        border: 1px solid var(--border);
        border-radius: 7px;
        overflow: hidden;
    }
    .inspect-item-head,
    .inspect-item-row {
        display: grid;
        grid-template-columns: 72px minmax(0, 1fr) 70px 95px 95px;
        gap: 0;
        align-items: center;
    }
    .inspect-item-head {
        background: var(--surface2);
        border-bottom: 1px solid var(--border);
    }
    .inspect-item-head div {
        font-size: .62rem;
        text-transform: uppercase;
        letter-spacing: .08em;
        color: var(--muted);
        font-weight: 700;
        padding: .52rem .58rem;
        border-right: 1px solid var(--border);
    }
    .inspect-item-head div:last-child { border-right: none; }
    .inspect-item-list {
        max-height: 300px;
        overflow: auto;
    }
    .inspect-item-row {
        border-bottom: 1px solid var(--border);
    }
    .inspect-item-row:last-child { border-bottom: none; }
    .inspect-item-row div {
        padding: .52rem .58rem;
        border-right: 1px solid var(--border);
        font-family: var(--mono);
        font-size: .8rem;
        min-width: 0;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .inspect-item-row div:last-child { border-right: none; }
    .inspect-item-row .num { text-align: right; }
    .inspect-empty {
        padding: 1rem;
        color: var(--muted);
        font-size: .85rem;
    }
    #modal-view .modal-footer {
        border-top: none;
        margin-top: 1rem;
        padding-top: 0;
    }
</style>
@endpush

@section('content')
<div class="layout">
    <aside>
        <div class="aside-label">Navigation</div>
        <a href="{{ route('dashboard') }}" class="aside-link">
            <span class="aside-icon">⬡</span> Dashboard
        </a>
        <a href="{{ route('products') }}" class="aside-link">
            <span class="aside-icon">◈</span> Products
        </a>
        <a href="{{ route('orders') }}" class="aside-link active">
            <span class="aside-icon">◳</span> Orders
        </a>
        <a href="{{ route('customers') }}" class="aside-link">
            <span class="aside-icon">🧙</span> Customers
        </a>
        <a href="#" class="aside-link">
            <span class="aside-icon">◫</span> Warehouse
        </a>
    </aside>

    <main>
        <div class="toolbar">
            <div class="search-wrap">
                <span class="search-icon">🔍</span>
                <input id="search" class="search-input" type="text" placeholder="Search by order, customer, date or item…">
            </div>

            <div class="spacer"></div>

            @if(Auth::user()->canWrite())
            <button class="btn-primary" id="btn-add">
                <span>＋</span> Add Order
            </button>
            @endif
        </div>

        <div class="tbl-head">
            <div class="th" data-sort="pAufNr">Order <span class="sort-arrow">↕</span></div>
            <div class="th" data-sort="customer_text">Customer <span class="sort-arrow">↕</span></div>
            <div class="th" data-sort="aufDat">Created <span class="sort-arrow">↕</span></div>
            <div class="th" data-sort="aufTermin">Due <span class="sort-arrow">↕</span></div>
            <div class="th" data-sort="item_count">Items <span class="sort-arrow">↕</span></div>
            <div class="th" data-sort="order_total">Qty <span class="sort-arrow">↕</span></div>
            <div class="th" data-sort="preis_total">Total € <span class="sort-arrow">↕</span></div>
            <div class="th">Actions</div>
        </div>

        <div class="vscroll-wrap" id="vscroll">
            <div class="vscroll-inner" id="vscroll-inner">
                <div class="loading-row"><div class="spinner"></div> Loading orders…</div>
            </div>
        </div>
    </main>
</div>

<div id="toast-area"></div>

<div class="overlay" id="modal-form-overlay">
    <div class="modal" id="modal-form">
        <div class="modal-title">
            <span id="modal-form-title">New Order</span>
            <span class="badge" id="modal-form-badge">CREATE</span>
        </div>

        <form id="order-form" autocomplete="off">
            <input type="hidden" id="f-id">

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Order Date <em style="color:var(--red)">*</em></label>
                    <input class="form-input" id="f-aufDat" type="datetime-local">
                    <div class="form-error" id="err-aufDat"></div>
                </div>

                <div class="form-group">
                    <label class="form-label">Delivery Date <em style="color:var(--red)">*</em></label>
                    <input class="form-input" id="f-aufTermin" type="datetime-local">
                    <div class="form-error" id="err-aufTermin"></div>
                </div>

                <div class="form-group form-full">
                    <label class="form-label">Customer <em style="color:var(--red)">*</em></label>
                    <select class="form-select" id="f-fKdNr"></select>
                    <div class="form-error" id="err-fKdNr"></div>
                </div>

                <div class="items-wrap">
                    <div class="items-head">
                        <div class="items-label">Items</div>
                        <button type="button" class="btn-cancel" id="btn-add-item">+ Add Item</button>
                    </div>

                    <div class="items-list" id="item-rows"></div>
                    <div class="form-error" id="err-items"></div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn-cancel" id="modal-form-cancel">Cancel</button>
                <button type="submit" class="btn-submit btn-submit-save" id="modal-form-submit">Save Order</button>
            </div>
        </form>
    </div>
</div>

<div class="overlay" id="modal-view-overlay">
    <div class="modal" id="modal-view">
        <div class="modal-title">
            <span>Order Details</span>
            <span class="badge" id="view-order-badge">#—</span>
        </div>

        <div class="inspect-meta">
            <div class="inspect-box">
                <div class="inspect-label">Customer</div>
                <div class="inspect-value" id="view-customer">—</div>
            </div>
            <div class="inspect-box">
                <div class="inspect-label">Created</div>
                <div class="inspect-value" id="view-created">—</div>
            </div>
            <div class="inspect-box">
                <div class="inspect-label">Delivery</div>
                <div class="inspect-value" id="view-due">—</div>
            </div>
            
            <div class="inspect-box">
                <div class="inspect-label">Total Products</div>
                <div class="inspect-value" id="view-total-products">—</div>
            </div>
            <div class="inspect-box">
                <div class="inspect-label">Total EUR</div>
                <div class="inspect-value" id="view-total-eur">—</div>
            </div>
        </div>

        <div class="inspect-items">
            <div class="inspect-item-head">
                <div>Pos</div>
                <div>Product</div>
                <div>Qty</div>
                <div>Unit €</div>
                <div>Total €</div>
            </div>
            <div class="inspect-item-list" id="view-items"></div>
        </div>

        <div class="modal-footer">
            <button type="button" class="btn-cancel" id="modal-view-close">Close</button>
        </div>
    </div>
</div>

<div class="overlay" id="modal-del-overlay">
    <div class="modal modal-sm">
        <div class="modal-title" style="color:var(--red)">⚠ Delete Order</div>
        <p class="confirm-body">
            This will delete order <span class="confirm-target" id="del-target-id"></span>
            and restore stock from its items.
        </p>
        <div class="modal-footer">
            <button type="button" class="btn-cancel" id="modal-del-cancel">Keep it</button>
            <button type="button" class="btn-submit btn-submit-delete" id="modal-del-confirm">Delete</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
    <script src="{{ asset('js/orders.js') }}"></script>
@endpush
