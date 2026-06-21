@php
    $canWrite    = Auth::user()->canWrite();
    $canDelete   = Auth::user()->canDelete();
    $showActions = $canWrite || $canDelete;
@endphp
<tr class="row-clickable purchase-order-row" data-row data-id="{{ $row['id'] }}">
    <td class="cell-id">#{{ $row['id'] }}</td>
    <td class="cell-name" title="{{ $row['supplier'] }}">{{ $row['supplier'] ?: '—' }}</td>
    <td class="cell-status purchase-orders-status"><span class="status-badge status-{{ $row['status'] }}">{{ $row['status'] }}</span></td>
    <td class="cell-date purchase-orders-date">{{ $row['ordered'] ?: '—' }}</td>
    <td class="cell-date purchase-orders-date">{{ $row['expected'] ?: '—' }}</td>
    <td class="cell-number">{{ $row['item_count'] }}</td>
    <td class="cell-money">€{{ number_format($row['total_value'], 2) }}</td>
    @if($showActions)
        <td class="cell-actions">
            <div class="table-actions">
                @if($canWrite && $row['is_editable'])
                    <button class="btn-icon purchase-order-edit" title="Edit" data-id="{{ $row['id'] }}">
                        <img class="action-icon" src="{{ asset('icons/lucide/pencil.png') }}" alt="Edit">
                    </button>
                @endif
                @if($canWrite && $row['is_receivable'])
                    <button class="btn-icon purchase-order-receive" title="Receive delivery" data-id="{{ $row['id'] }}">
                        <img class="action-icon" src="{{ asset('icons/lucide/list-checks.png') }}" alt="Receive delivery">
                    </button>
                @endif
                @if($canDelete && $row['is_editable'])
                    <button class="btn-icon del purchase-order-delete" title="Cancel" data-id="{{ $row['id'] }}">
                        <img class="action-icon" src="{{ asset('icons/lucide/trash-2.png') }}" alt="Cancel">
                    </button>
                @endif
            </div>
        </td>
    @endif
</tr>
