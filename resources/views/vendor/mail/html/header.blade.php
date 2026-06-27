@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
<img src="{{ asset('images/hat.png') }}" class="logo" alt="BiggerHat">
<div class="logo-wordmark">{{ $slot }}</div>
</a>
</td>
</tr>
