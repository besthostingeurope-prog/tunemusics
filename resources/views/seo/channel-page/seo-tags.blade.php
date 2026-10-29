<meta property="og:site_name" content="{{ settings('branding.site_name') }}" />
<meta property="twitter:card" content="summary" />
<meta property="og:type" content="website" />

@if (isset($channel['config']['seoTitle']))
    <title>{{ $channel['config']['seoTitle'] }}</title>
    <meta property="og:title" content="{{ $channel['config']['seoTitle'] }}" />
@else
    <title>{{ settings('branding.site_name') }}</title>
@endif

<meta property="og:url" content="{{ urls()->channel($channel) }}" />
<link rel="canonical" href="{{ urls()->channel($channel) }}" />

<meta property="og:image" content="{{ url('og-image.png') }}?v=2" />
<meta property="og:image:width" content="888" />
<meta property="og:image:height" content="888" />

@if (isset($channel['config']['seoDescription']))
    <meta
        property="og:description"
        content="{{ $channel['config']['seoDescription'] }}"
    />
    <meta
        name="description"
        content="{{ $channel['config']['seoDescription'] }}"
    />
@endif
