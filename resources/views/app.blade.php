<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Favicon -->
    <link rel="icon" href="{{ asset('superspotwifi-logo.png') }}" type="image/x-icon">
    <title inertia>{{ config('app.name', 'SuperSpot Wifi ') }}</title>
    <meta name="description" content="Easily and securely buy your SuperSpot Wifi  internet voucher online. Fast, convenient, and reliable internet access in just a few steps." />
    <meta name="keywords" content="SuperSpot Wifi , internet voucher, Uganda internet, hotspot, voucher purchase, fast internet, online payment" />
    <meta name="author" content="SuperSpot Wifi " />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <!-- Open Graph / Facebook -->
    <meta property="og:title" content="Buy Internet Voucher - SuperSpot Wifi " />
    <meta property="og:description" content="Buy your SuperSpot Wifi  internet voucher securely and instantly online." />
    <meta property="og:image" content="{{ asset('superspotwifi-logo.png') }}" />
    <meta property="og:url" content="{{ url('/')}}" />
    <meta property="og:type" content="website" />

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="Buy Internet Voucher - SuperSpot Wifi " />
    <meta name="twitter:description" content="Buy your SuperSpot Wifi  internet voucher securely and instantly online." />
    <meta name="twitter:image" content="{{ asset('superspotwifi-logo.png') }}" />


    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
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