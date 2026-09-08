@php
    $type = $type ?? 'info';
    $config = match ($type) {
        'danger', 'error' => [
            'bg'     => '#fef2f2',
            'border' => '#dc2626',
            'title'  => '#991b1b',
            'text'   => '#7f1d1d',
        ],
        'warning' => [
            'bg'     => '#fffbeb',
            'border' => '#d97706',
            'title'  => '#92400e',
            'text'   => '#78350f',
        ],
        'success' => [
            'bg'     => '#f4f6ec',
            'border' => '#557116',
            'title'  => '#3e530f',
            'text'   => '#4d6614',
        ],
        default => [
            'bg'     => '#f9fafb',
            'border' => '#9ca3af',
            'title'  => '#1f2937',
            'text'   => '#4b5563',
        ],
    };
@endphp
<table width="100%" cellpadding="14" cellspacing="0" border="0" style="background-color: {{ $config['bg'] }}; border-left: 4px solid {{ $config['border'] }}; border-radius: 4px; margin: 16px 0 20px;">
    <tr>
        <td style="font-family: 'Plus Jakarta Sans', Arial, sans-serif; font-size: 13px; line-height: 1.5; color: {{ $config['text'] }};">
            @if(!empty($title))
                <strong style="color: {{ $config['title'] }}; display: block; margin-bottom: 4px;">{{ $title }}</strong>
            @endif
            {{ $message ?? $slot ?? '' }}
        </td>
    </tr>
</table>
