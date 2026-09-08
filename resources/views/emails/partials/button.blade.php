@php
    $color = $color ?? 'primary';
    $bgColor = match ($color) {
        'orange' => '#F86B17',
        'danger' => '#dc2626',
        default  => '#557116',
    };
@endphp
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 20px 0;">
    <tr>
        <td align="{{ $align ?? 'center' }}">
            <table cellpadding="0" cellspacing="0" border="0">
                <tr>
                    <td align="center" style="background-color: {{ $bgColor }}; border-radius: 12px; box-shadow: 0 2px 6px rgba(0,0,0,0.1);">
                        <a href="{{ $url ?? '#' }}" target="_blank" style="display: inline-block; padding: 12px 28px; font-family: 'Plus Jakarta Sans', Arial, sans-serif; font-size: 14px; font-weight: 700; color: #ffffff; text-decoration: none; border-radius: 12px; letter-spacing: 0.2px;">
                            {{ $text ?? 'View in App' }} &rarr;
                        </a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
