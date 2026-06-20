@php
    $canWrite    = Auth::user()->canWrite();
    $showActions = $canWrite;
    $name        = $row['name'] ?: ('Group ' . $row['id']);
@endphp
<tr class="row-clickable" data-row data-group-id="{{ $row['id'] }}" data-group-name="{{ $name }}">
    <td class="cell-id">#{{ $row['id'] }}</td>
    <td class="cell-name" title="{{ $row['name'] }}">{{ $row['name'] ?: '—' }}</td>
    @if($showActions)
        <td class="cell-actions">
            <div class="table-actions">
                <button class="btn-icon group-edit" title="Edit" data-id="{{ $row['id'] }}" data-name="{{ $row['name'] }}">
                    <img class="action-icon" src="{{ asset('icons/lucide/pencil.png') }}" alt="Edit">
                </button>
            </div>
        </td>
    @endif
</tr>
