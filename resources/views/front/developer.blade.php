@extends('layouts.front')

@section('title', 'Developer Tools — REST API, Git, SSH a více')
@section('meta_description', 'Hosting pro vývojáře. SSH přístup, Git deploy, REST API, Composer, Node.js, WP-CLI, staging prostředí a automatizace. Vše co potřebujete pro moderní vývoj.')

@section('content')

    {{-- HERO --}}
    <div class="top-header overlay">
        <div class="container">
            <div class="row">
                <div class="col-sm-12 col-md-12">
                    <div class="wrapper">
                        <h1 class="heading text-center">Developer Tools</h1>
                        <div class="subheading text-center">Hosting navržený pro vývojáře — SSH, Git, REST API a plná kontrola nad prostředím.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- FEATURE HIGHLIGHT --}}
    <section class="history-section feat01 sec-normal bg-colorstyle">
        <div class="container">
            <div class="randomline">
                <div class="bigline"></div>
                <div class="smallline"></div>
            </div>
            <div class="sec-main sec-bg1 bg-colorstyle noshadow nopadding">
                <div class="row align-items-center">
                    <div class="col-md-12 col-lg-6">
                        <div class="info-content">
                            <h2 class="fw-bold mb-3 mergecolor">Moderní stack pro moderní projekty</h2>
                            <p class="seccolor">OnHost poskytuje vývojářům plný přístup k serverovému prostředí. Nasazujte přes Git, spravujte závislosti přes Composer a automatizujte deployment pomocí webhooků.</p>
                        </div>
                        <ul class="list-unstyled seccolor mt-3">
                            <li class="py-1"><i class="fas fa-check-circle purple me-2"></i>PHP 8.1, 8.2, 8.3 — přepínání za běhu</li>
                            <li class="py-1"><i class="fas fa-check-circle purple me-2"></i>SSH přístup — plná kontrola</li>
                            <li class="py-1"><i class="fas fa-check-circle purple me-2"></i>Git deploy — push = deploy</li>
                            <li class="py-1"><i class="fas fa-check-circle purple me-2"></i>Composer, WP-CLI, Node.js</li>
                            <li class="py-1"><i class="fas fa-check-circle purple me-2"></i>Staging prostředí — testujte před nasazením</li>
                            <li class="py-1"><i class="fas fa-check-circle purple me-2"></i>REST API pro správu účtu a služeb</li>
                        </ul>
                        <a href="{{ route('front.webhosting') }}" class="btn btn-default-yellow-fill mt-3">Zobrazit hostingové tarify</a>
                    </div>
                    <div class="col-md-12 col-lg-5 offset-lg-1 mt-4 mt-lg-0">
                        <div class="wrapper bg-seccolorstyle p-4 rounded">
                            <h6 class="mergecolor mb-3">Ukázka Git deploy workflow</h6>
                            <pre class="text-start seccolor f-13 mb-0" style="background:transparent;overflow:auto"><code># Přidejte OnHost jako remote
git remote add onhost \
  ssh://onhost.cz/~/repo.git

# Push = automatický deploy
git push onhost main

# Výstup
remote: Deploying to production...
remote: Composer install OK
remote: Migrations OK
remote: ✓ Deploy complete</code></pre>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- TOOLS GRID --}}
    <section class="services sec-normal motpath sec-bg4">
        <div class="container">
            <div class="service-wrap">
                <div class="row">
                    <div class="col-sm-12 text-center">
                        <h2 class="section-heading">Dostupné nástroje</h2>
                        <p class="section-subheading">Vše co vývojář potřebuje — předinstalované a připravené k použití.</p>
                    </div>
                    @foreach([
                        ['icon' => 'icon-git',       'badge' => 'Git',      'title' => 'Git & Deploy',        'text' => 'Push-to-deploy přes SSH. Automatické spuštění Composer install a migrací.'],
                        ['icon' => 'icon-cpu',       'badge' => 'PHP',      'title' => 'PHP 8.3 + Extensions', 'text' => 'Vyberte verzi PHP, nastavte php.ini, aktivujte rozšíření — vše přes panel.'],
                        ['icon' => 'ico-database',   'badge' => 'MySQL',    'title' => 'MySQL & MariaDB',      'text' => 'Plný přístup přes phpMyAdmin nebo SSH tunnel. Importy bez limitů.'],
                        ['icon' => 'icon-speed',      'badge' => 'Cache',    'title' => 'Redis & Memcached',    'text' => 'Sdílené cache instance pro session, frontu a objektový cache.'],
                        ['icon' => 'icon-lock',        'badge' => 'SSL',      'title' => 'Let\'s Encrypt Auto',  'text' => 'Automatické SSL pro všechny domény a subdomény — bez manuálního zásahu.'],
                        ['icon' => 'icon-support',    'badge' => 'API',      'title' => 'REST API',             'text' => 'Spravujte DNS, domény, služby a monitorig programově přes REST API.'],
                    ] as $f)
                        <div class="col-sm-12 col-md-4" data-aos="fade-up">
                            <div class="service-section bg-colorstyle">
                                <div class="plans badge feat bg-purple">{{ $f['badge'] }}</div>
                                <i class="{{ $f['icon'] }} f-30 purple mb-3 d-block"></i>
                                <div class="title mergecolor">{{ $f['title'] }}</div>
                                <p class="subtitle seccolor">{{ $f['text'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    {{-- HELP --}}
    <section class="services help sec-bg2 pt-4 pb-80 bg-colorstyle">
        <div class="container">
            <div class="service-wrap">
                <div class="row">
                    <div class="col-sm-12 col-md-6 col-lg-4">
                        <div class="help-container bg-seccolorstyle">
                            <a href="{{ route('front.kb') }}" class="help-item" title="Dokumentace">
                                <div class="img"><i class="icon-drives f-40 purple"></i></div>
                                <div class="inform">
                                    <div class="title mergecolor">Dokumentace API</div>
                                    <div class="description seccolor">Kompletní referenční dokumentace REST API s příklady.</div>
                                </div>
                            </a>
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-6 col-lg-4">
                        <div class="help-container bg-seccolorstyle">
                            <a href="{{ route('front.webhosting') }}" class="help-item" title="Tarify">
                                <div class="img"><i class="icon-cpu f-40 purple"></i></div>
                                <div class="inform">
                                    <div class="title mergecolor">Hostingové tarify</div>
                                    <div class="description seccolor">Vyberte tarif s SSH přístupem a Git deploy workflow.</div>
                                </div>
                            </a>
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-6 col-lg-4">
                        <div class="help-container bg-seccolorstyle">
                            <a href="{{ route('front.contact') }}" class="help-item" title="Podpora">
                                <div class="img"><i class="icon-support f-40 purple"></i></div>
                                <div class="inform">
                                    <div class="title mergecolor">Technická podpora</div>
                                    <div class="description seccolor">Naši technici znají PHP, Laravel i Nginx — rádi pomůžou.</div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

@endsection
