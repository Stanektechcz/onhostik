<!DOCTYPE html>
{{-- Standalone — must render even when app layout cannot --}}
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>500 — Chyba serveru | Onhost.cz</title>
    <link rel="icon" href="/panel/images/favicon.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css?family=Rubik:400,400i,500,500i,700,700i&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/panel/css/vendors/feather-icon.css">
    <link rel="stylesheet" href="/panel/css/style.css">
</head>
<body>
<div class="tap-top"><i data-feather="chevrons-up"></i></div>
<div class="page-wrapper compact-wrapper" id="pageWrapper">
    <div class="error-wrapper">
        <div class="container">
            <svg><use href="/panel/svg/icon-sprite.svg#error-500"></use></svg>
            <div class="grid grid-cols-12">
                <div class="col-start-4 md:col-start-0 col-span-6 md:col-span-12">
                    <h3>Chyba serveru</h3>
                    <p class="sub-content pb-0">
                        Server nemohl dokončit zpracování vašeho požadavku.
                        Zkuste to prosím za chvíli nebo kontaktujte podporu.
                    </p>
                </div>
            </div>
            <div>
                <a class="btn btn-primary btn-lg text-white hover:text-white !rounded-lg me-2" href="/">Domovská stránka</a>
                <a class="btn btn-outline-primary btn-lg !rounded-lg" href="/panel">Zákaznický panel</a>
            </div>
        </div>
    </div>
</div>
<script src="/panel/js/jquery.min.js"></script>
<script src="/panel/js/icons/feather-icon/feather.min.js"></script>
<script src="/panel/js/icons/feather-icon/feather-icon.js"></script>
<script src="/panel/js/script.js"></script>
</body>
</html>
