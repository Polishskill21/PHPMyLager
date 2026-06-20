@php
    $canWrite  = Auth::user()->canWrite();
    $canDelete = Auth::user()->canDelete();
    $stockIcon = $row['state'] === 'warn' ? '◐' : '●';
@endphp
<tr data-row>
    <td class="cell-id">#{{ $row['id'] }}</td>
    <td class="cell-name" title="{{ $row['name'] }}">{{ $row['name'] ?: '—' }}</td>
    <td class="cell-muted" title="{{ $row['group_name'] }}">{{ $row['group_name'] }}</td>
    <td class="cell-money">{{ number_format($row['ek'], 2) }}</td>
    <td class="cell-money">{{ number_format($row['vk'], 2) }}</td>
    <td class="cell-status"><span class="stock-badge stock-{{ $row['state'] }}">{{ $stockIcon }} {{ $row['bestand'] }}</span></td>
    <td class="cell-number">{{ $row['melde'] }}</td>
    <td class="cell-mono" title="{{ $row['lagerplatz'] }}">{{ $row['lagerplatz'] ?: '—' }}</td>
    <td class="cell-actions">
        <div class="table-actions">
            @if($canWrite)
                <button class="btn-icon product-edit" title="Edit" data-id="{{ $row['id'] }}">
                    <img class="action-icon" src="{{ asset('icons/lucide/pencil.png') }}" alt="Edit">
                </button>
            @endif
            @if($canDelete)
                <button class="btn-icon product-adjust" title="Adjust stock" data-id="{{ $row['id'] }}" data-name="{{ $row['name'] }}" data-stock="{{ $row['bestand'] }}">
                    <img class="action-icon" src="{{ asset('icons/lucide/list-checks.png') }}" alt="Adjust stock">
                </button>
            @endif
            @if($row['has_history'])
                <button class="btn-icon product-history" title="Stock history" data-id="{{ $row['id'] }}" data-name="{{ $row['name'] }}">
                    <img class="action-icon" src="{{ asset('icons/lucide/clipboard-clock.png') }}" alt="Stock history">
                </button>
            @endif
            @if($canDelete)
                <button class="btn-icon del product-delete" title="Discontinue" data-id="{{ $row['id'] }}" data-name="{{ $row['name'] }}">
                    <img class="action-icon" src="{{ asset('icons/lucide/trash-2.png') }}" alt="Discontinue">
                </button>
            @endif
        </div>
    </td>
</tr>
