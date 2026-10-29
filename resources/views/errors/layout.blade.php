<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('title') - TuneMusics</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{min-height:100vh;display:flex;align-items:center;justify-content:center;background:#0a0a0f;color:#fff;font-family:'Segoe UI',Roboto,Arial,sans-serif;text-align:center;padding:24px}
.card{max-width:520px}
.icon{width:110px;height:110px;margin:0 auto 28px;border-radius:50%;background:radial-gradient(circle at 30% 30%,#e91e63,#880e4f);display:flex;align-items:center;justify-content:center;font-size:52px;box-shadow:0 10px 40px rgba(233,30,99,.35);animation:pulse 2s infinite}
@keyframes pulse{0%,100%{transform:scale(1)}50%{transform:scale(1.06)}}
h1{font-size:26px;margin-bottom:12px}
p{color:#b8b8c4;font-size:16px;line-height:1.6;margin-bottom:28px}
a.btn{display:inline-block;background:#e91e63;color:#fff;text-decoration:none;padding:13px 34px;border-radius:999px;font-weight:600;font-size:15px;transition:.2s}
a.btn:hover{background:#f0367a;transform:translateY(-1px)}
.brand{margin-top:36px;color:#5a5a68;font-size:13px;letter-spacing:2px;text-transform:uppercase}
</style>
</head>
<body>
<div class="card">
<div class="icon"></div>
<h1>@yield('heading')</h1>
<p>@yield('message')</p>
<a class="btn" href="/">@yield('button', 'Back to the music')</a>
<div class="brand">TuneMusics</div>
</div>
</body>
</html>
