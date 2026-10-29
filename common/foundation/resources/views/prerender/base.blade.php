<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="google" content="notranslate">
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5">
        <base href="{{ $htmlBaseUri }}">

        @yield('head')

        @include('common::prerender.meta-tags')
    </head>

    <body>
        <main>@yield('body')</main>
    </body>
</html>
