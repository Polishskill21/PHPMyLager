@php
    $canWrite  = Auth::user()->canWrite();
    $canDelete = Auth::user()->canDelete();
@endphp
<tr class="row-clickable order-row" data-row data-id="{{ $row['id'] }}">
    <td class="cell-id">#{{ $row['id'] }}</td>
    <td class="cell-customer" title="{{ $row['customer'] }}">{{ $row['customer'] }}</td>
    <td class="cell-date">{{ $row['created'] ?: '—' }}</td>
    <td class="cell-date">{{ $row['delivery'] ?: '—' }}</td>
    <td class="cell-number td-items">{{ $row['item_count'] }}</td>
    <td class="cell-money td-total">€{{ number_format($row['total'], 2) }}</td>
    <td class="cell-actions">
        <div class="orders-actions">
            @if($canWrite)
                <button class="btn-icon order-edit" title="Edit" data-id="{{ $row['id'] }}">
                    <img class="action-icon" src="{{ asset('icons/lucide/pencil.png') }}" alt="Edit">
                </button>
            @endif
            @if($canDelete)
                <button class="btn-icon del order-delete" title="Delete" data-id="{{ $row['id'] }}">
                    <img class="action-icon" src="{{ asset('icons/lucide/trash-2.png') }}" alt="Delete">
                </button>
            @endif
        </div>
    </td>
</tr>
