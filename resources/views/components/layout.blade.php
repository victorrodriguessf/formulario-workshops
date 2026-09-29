@props(['title' => 'Cursos e oficinas', 'step' => null, 'admin' => false, 'wide' => false])
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0b315f">
    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">
    <title>{{ $title }} | Armarinho Ponto a Ponto</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="{{ $admin ? 'admin-page' : 'public-page' }}">
    <a class="skip-link" href="#conteudo">Pular para o conteúdo</a>
    <header class="site-header">
        <div class="header-inner">
            <a class="brand" href="{{ $admin && auth()->check() ? route('admin.events.index') : route('events.index') }}">
                <img class="brand-logo" src="{{ asset('images/logo-horizontal.png') }}" alt="Armarinho Ponto a Ponto" width="1078" height="492">
            </a>
            @if($admin && auth()->check())
                <nav class="header-nav" aria-label="Navegação administrativa">
                    <a href="{{ route('admin.events.index') }}" @if(request()->routeIs('admin.events.*')) aria-current="page" @endif>Cursos</a>
                    <a href="{{ route('admin.registrations.index') }}" @if(request()->routeIs('admin.registrations.*')) aria-current="page" @endif>Inscrições</a>
                    <form action="{{ route('admin.logout') }}" method="POST">@csrf<button type="submit" class="nav-logout">Sair</button></form>
                </nav>
            @endif
        </div>
    </header>
    <main id="conteudo" class="page-shell {{ $wide ? 'page-shell-wide' : '' }}">
        @if($step)
            @php($stepLabels = ['Seus dados', 'Conferir dados', 'Realizar pagamento', 'Inscrição confirmada'])
            <p class="step-status">Etapa {{ $step }} de 4: <strong>{{ $stepLabels[$step - 1] }}</strong></p>
            <nav class="steps" aria-label="Etapas da inscrição">
                @foreach($stepLabels as $number => $label)
                    <span class="step {{ $step === $number + 1 ? 'is-current' : '' }} {{ $step > $number + 1 ? 'is-done' : '' }}" @if($step === $number + 1) aria-current="step" @endif>
                        <span class="step-number">{{ $number + 1 }}</span><span class="step-label">{{ $label }}</span>
                    </span>
                @endforeach
            </nav>
        @endif
        {{ $slot }}
    </main>
    <footer class="site-footer">
        <div class="footer-inner">
            <div class="footer-brandline"><strong>Armarinho Ponto a Ponto</strong><span>Aonde as ideias criam formas!</span></div>
            <div class="footer-legal">
                <div class="footer-legal-text">
                    <p>© {{ now()->year }} Armarinho Ponto a Ponto. Todos os direitos reservados.</p>
                    <p>Os textos, imagens e elementos visuais originais deste site são protegidos pela <a href="https://www.planalto.gov.br/ccivil_03/leis/l9610.htm">Lei de Direitos Autorais (Lei nº 9.610/1998)</a>. O uso da identidade da marca observa a <a href="https://www.planalto.gov.br/ccivil_03/leis/l9279.htm">Lei da Propriedade Industrial (Lei nº 9.279/1996)</a>. Reprodução ou uso comercial dependem de autorização, respeitadas as exceções previstas em lei.</p>
                </div>
                <img class="footer-logo" src="{{ asset('images/logo-fundobranco.png') }}" alt="Armarinho Ponto a Ponto" width="1479" height="1063" loading="lazy">
            </div>
        </div>
    </footer>
</body>
</html>
