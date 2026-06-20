@php
    $canWrite    = Auth::user()->canWrite();
    $canDelete   = Auth::user()->canDelete();
    $showActions = $canWrite || $canDelete;
@endphp
<tr data-row>
    <td class="cell-id">#{{ $row['id'] }}</td>
    <td class="cell-name" title="{{ $row['name'] }}">{{ $row['name'] ?: '—' }}</td>
    <td class="cell-muted" title="{{ $row['email'] }}">{{ $row['email'] ?: '—' }}</td>
    <td class="cell-muted" title="{{ $row['street'] }}">{{ $row['street'] ?: '—' }}</td>
    <td class="cell-muted" title="{{ $row['city'] }}">{{ $row['city'] ?: '—' }}</td>
    <td class="cell-mono" title="{{ $row['plz'] }}">{{ $row['plz'] ?: '—' }}</td>
    @if($showActions)
        <td class="cell-actions">
            <div class="table-actions">
                @if($canWrite)
                    <button class="btn-icon supplier-edit" title="Edit" data-id="{{ $row['id'] }}">
                        <img class="action-icon" src="{{ asset('icons/lucide/pencil.png') }}" alt="Edit">
                    </button>
                @endif
                @if($canDelete)
                    <button class="btn-icon del supplier-delete" title="Delete" data-id="{{ $row['id'] }}" data-name="{{ $row['name'] }}">
                        <img class="action-icon" src="{{ asset('icons/lucide/trash-2.png') }}" alt="Delete">
                    </button>
                @endif
            </div>
        </td>
    @endif
</tr>
