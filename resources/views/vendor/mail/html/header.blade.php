@props(['url'])
<tr>
<td class="header">
<table role="presentation" cellpadding="0" cellspacing="0" style="margin: 0 auto;">
<tr>
<td><a href="{{ $url }}"><img src="{{ asset('apple-touch-icon.png') }}" width="32" height="32" class="logo" alt=""></a></td>
<td class="logo-name"><a href="{{ $url }}">{!! $slot !!}</a></td>
</tr>
</table>
</td>
</tr>
