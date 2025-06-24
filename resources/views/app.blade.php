<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Favicon -->
    <link rel="icon" href="{{ asset('zybernet-full-logo.png') }}" type="image/x-icon">
    <title inertia>{{ config('app.name', 'ZyberNet') }}</title>
    <meta name="description" content="Easily and securely buy your ZyberNet internet voucher online. Fast, convenient, and reliable internet access in just a few steps." />
    <meta name="keywords" content="ZyberNet, internet voucher, Uganda internet, hotspot, voucher purchase, fast internet, online payment" />
    <meta name="author" content="ZyberNet" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <!-- Open Graph / Facebook -->
    <meta property="og:title" content="Buy Internet Voucher - ZyberNet" />
    <meta property="og:description" content="Buy your ZyberNet internet voucher securely and instantly online." />
    <meta property="og:image" content="{{ asset('zybernet-full-logo.png') }}" />
    <meta property="og:url" content="{{ url('/')}}" />
    <meta property="og:type" content="website" />

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="Buy Internet Voucher - ZyberNet" />
    <meta name="twitter:description" content="Buy your ZyberNet internet voucher securely and instantly online." />
    <meta name="twitter:image" content="{{ asset('zybernet-full-logo.png') }}" />


    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <!-- Scripts -->
    @routes
    @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
    @inertiaHead
</head>

<body class="font-sans antialiased">
    @inertia
</body>

</html>