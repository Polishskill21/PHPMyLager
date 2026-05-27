@extends('layouts.app')

@section('title', 'Product Groups - PhpMyLager')
@section('page-title', 'Product Groups')

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
    .tbl-head { display: grid; grid-template-columns: 90px 1fr 120px; gap: 0; padding: 0 .75rem; border: 1px solid var(--border); border-radius: 7px 7px 0 0; background: var(--surface); flex-shrink: 0; }
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
    .row { position: absolute; left: 0; right: 0; height: var(--row-h); display: grid; grid-template-columns: 90px 1fr 120px; border-bottom: 1px solid var(--border); align-items: center; padding: 0 .75rem; transition: background .1s; }
    .row:hover { background: rgba(59, 130, 246, 0.04); }
    .row-clickable { cursor: pointer; }
    .cell { padding: 0 .5rem; font-size: .82rem; font-family: var(--mono); color: var(--text); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; border-right: 1px solid var(--border); }
    .cell:last-child { border-right: none; }
    .cell-id { color: var(--muted); font-size: .75rem; }
    .cell-name { font-family: var(--sans); font-weight: 600; font-size: .85rem; }

    .actions { display: flex; gap: .4rem; align-items: center; justify-content: flex-end; }
    .btn-icon { width: 28px; height: 28px; border-radius: 5px; border: 1px solid var(--border); background: transparent; color: var(--muted); font-size: .82rem; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all .15s; }
    .btn-icon:hover.edit  { border-color: var(--accent); color: var(--accent); background: rgba(59, 130, 246, 0.1); }

    .empty-state { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 4rem; gap: .75rem; color: var(--muted); }
    .empty-state .icon { font-size: 2.5rem; opacity: .4; }
    .empty-state p { font-size: .9rem; }

    .loading-row { display: flex; align-items: center; justify-content: center; height: 120px; color: var(--muted); font-size: .85rem; gap: .6rem; }
    .spinner { width: 18px; height: 18px; border: 2px solid var(--border2); border-top-color: var(--accent); border-radius: 50%; animation: spin .7s linear infinite; }
    @keyframes spin { to { transform: rotate(360deg); } }

    /* ── PRODUCTS LIST ── */
    .list-table { border: 1px solid var(--border); border-radius: 8px; overflow: hidden; }
    .list-head, .list-row { display: grid; grid-template-columns: 90px 1fr; }
    .list-head { background: var(--surface2); border-bottom: 1px solid var(--border); }
    .list-cell { padding: .6rem .75rem; font-size: .78rem; font-family: var(--mono); color: var(--muted); border-right: 1px solid var(--border); }
    .list-cell:last-child { border-right: none; }
    .list-row .list-cell { color: var(--text); font-size: .82rem; }
    .list-row:nth-child(even) { background: rgba(59, 130, 246, 0.04); }
    .list-body { max-height: 350px; overflow-y: auto; }
    .list-body::-webkit-scrollbar { width: 6px; }
    .list-body::-webkit-scrollbar-track { background: transparent; }
    .list-body::-webkit-scrollbar-thumb { background: var(--border2); border-radius: 3px; }
    .list-empty { padding: 1rem; color: var(--muted); font-size: .85rem; text-align: center; }
</style>
@endpush

@section('content')
<div class="layout">
    <aside>
        <div class="aside-label">Navigation</div>
        <a href="{{ route('dashboard') }}" class="aside-link">
            <span class="aside-icon">🏠</span> Dashboard
        </a>
        <a href="{{ route('products') }}" class="aside-link">
            <span class="aside-icon">📦</span> Products
        </a>
        <a href="{{ route('orders') }}" class="aside-link">
            <span class="aside-icon">🗂️</span> Orders
        </a>
        <a href="{{ route('customers') }}" class="aside-link">
            <span class="aside-icon">🧙</span> Customers
        </a>
        <a href="{{ route('warehouse') }}" class="aside-link active">
            <span class="aside-icon">🧺</span> Product Groups
        </a>
    </aside>

    <main>
        <div class="toolbar">
            <div class="search-wrap">
                <span class="search-icon">🔍</span>
                <input id="search" class="search-input" type="text" placeholder="Search by ID or name…">
            </div>

            <div class="spacer"></div>

            @if(Auth::user()->canWrite())
            <button class="btn-primary" id="btn-add">
                <span>＋</span> Add Product Group
            </button>
            @endif
        </div>

        <div class="tbl-head">
            <div class="th" data-sort="pWgNr">ID <span class="sort-arrow">↕</span></div>
            <div class="th" data-sort="warengruppe">Product Group <span class="sort-arrow">↕</span></div>
            <div class="th">Actions</div>
        </div>

        <div class="vscroll-wrap" id="vscroll">
            <div class="vscroll-inner" id="vscroll-inner">
                <div class="loading-row"><div class="spinner"></div> Loading product groups…</div>
            </div>
        </div>
    </main>
</div>

<div id="toast-area"></div>

<div class="overlay" id="modal-form-overlay">
    <div class="modal" id="modal-form">
        <div class="modal-title">
            <span id="modal-form-title">New Product Group</span>
            <span class="badge" id="modal-form-badge">CREATE</span>
        </div>
        <form id="group-form" autocomplete="off">
            <input type="hidden" id="f-id">
            <div class="form-grid">
                <div class="form-group form-full">
                    <label class="form-label">Product Group Name <em style="color:var(--red)">*</em></label>
                    <input class="form-input" id="f-warengruppe" maxlength="50" placeholder="e.g. Electronics">
                    <div class="form-error" id="err-warengruppe"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" id="modal-form-cancel">Cancel</button>
                <button type="submit" class="btn-submit btn-submit-save" id="modal-form-submit">Save Product Group</button>
            </div>
        </form>
    </div>
</div>

<div class="overlay" id="modal-view-overlay">
    <div class="modal">
        <div class="modal-title">
            <span id="modal-view-title">Product Group</span>
            <span class="badge" id="modal-view-badge">—</span>
        </div>
        <p class="confirm-body">Products in this group: <span class="confirm-target" id="group-products-count">0</span></p>
        <div class="list-table" id="group-products-table">
            <div class="list-head">
                <div class="list-cell">ID</div>
                <div class="list-cell">Name</div>
            </div>
            <div id="group-products-list" class="list-body"></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-cancel" id="modal-view-close">Close</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
    <script src="{{ asset('js/warehouse.js') }}"></script>
@endpush
