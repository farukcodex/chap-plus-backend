@php
    $type = $type ?? 'success';
    $styles = match ($type) {
        'success', 'green' => 'background-color: #f4f6ec; color: #557116; border: 1px solid #e1ebc8;',
        'orange'           => 'background-color: #fff7ed; color: #F86B17; border: 1px solid #ffedd5;',
        'danger', 'error'  => 'background-color: #fef2f2; color: #dc2626; border: 1px solid #fee2e2;',
        'warning', 'amber' => 'background-color: #fffbeb; color: #d97706; border: 1px solid #fef3c7;',
        'info', 'blue'     => 'background-color: #eff6ff; color: #2563eb; border: 1px solid #dbeafe;',
        default            => 'background-color: #f3f4f6; color: #4b5563; border: 1px solid #e5e7eb;',
    };
@endphp
<table cellpadding="0" cellspacing="0" border="0" style="display: inline-table; vertical-align: middle;">
    <tr>
        <td style="padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; font-family: 'Plus Jakarta Sans', Arial, sans-serif; {!! $styles !!}">
            @if(!empty($icon)){{ $icon }} @endif{{ $text ?? $slot ?? '' }}
        </td>
    </tr>
</table>
