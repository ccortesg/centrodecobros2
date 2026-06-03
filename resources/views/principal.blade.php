<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="Centro de Cobros">
    <meta name="author" content="soportetech.com.mx">
    <meta name="keyword" content="Centro, Cobros, Masivo">
    <link rel="shortcut icon" href="img/favicon.png">
    <meta name="userId" content="{{ Auth::check() ? Auth::user()->id : ''}}">
    <title>Centro de Cobros Masivo</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link type="text/javascript" rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.7.2/Chart.min.js">
    
    <!-- Icons -->
    <link href="css/plantilla.css" rel="stylesheet">
    <style scoped>
        .loader{ 
            position: absolute;
            top:0px;
            right:0px;
            width:100%;
            height:100%;
            background-color:#eceaea;
            background-image: url('img/ajax-loader-blue.gif');
            background-size: 50px;
            background-repeat:no-repeat;
            background-position:center;
            z-index:10000000;
            opacity: 0.4;
            filter: alpha(opacity=40);
        }
    </style>
</head>

<body class="app header-fixed sidebar-fixed aside-menu-fixed aside-menu-hidden">
    <div id="app">
    <header class="app-header navbar" data-shell-header="authenticated">
        <button class="navbar-toggler mobile-sidebar-toggler d-lg-none mr-auto" type="button">
          <span class="navbar-toggler-icon"></span>
        </button>
        <a class="navbar-brand" href="#" data-shell-link="noop" data-shell-modern></a>
        <button class="navbar-toggler sidebar-toggler d-md-down-none" type="button">
          <span class="navbar-toggler-icon"></span>
        </button>
        <!--<ul class="nav navbar-nav d-md-down-none">
            <li class="nav-item px-3">
                <a class="nav-link" href="#">Escritorio</a>
            </li>
            <li class="nav-item px-3">
                <a class="nav-link" href="#">Configuraciones</a>
            </li>
        </ul>-->
        <ul class="nav navbar-nav ml-auto">
            <notification :notifications="notifications"></notification>
            <li class="nav-item dropdown" data-shell-dropdown>
                <a class="nav-link dropdown-toggle nav-link" data-shell-dropdown-toggle="account" data-shell-modern href="#" role="button" aria-haspopup="true" aria-expanded="false">
                    <span class="d-md-down-none">{{Auth::user()->usuario}} </span>
                </a>
                <div class="dropdown-menu dropdown-menu-right" data-shell-dropdown-menu>
                    <div class="dropdown-header text-center">
                        <strong>Cuenta</strong>
                    </div>
                    <a class="dropdown-item" href="{{ route('logout') }}" 
                    onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                    <i class="fa fa-lock"></i> Cerrar sesión</a>

                    <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
                        {{ csrf_field() }}
                    </form>
                </div>
            </li>
        </ul>
    </header>

    <div class="app-body">
        
        @if(Auth::check())
            @if (Auth::user()->idrol == 1)
                @include('plantilla.sidebaradministrador')
            @elseif (Auth::user()->idrol == 2)
                @include('plantilla.sidebarcliente')
            @else
             
            @endif

        @endif
        <!-- Contenido Principal -->
        @yield('contenido')
        <!-- /Fin del contenido principal -->
    </div>   
    </div>
    <footer class="app-footer">
        <span>Sistema de Centro de Cobros SIT &copy; 2022</span>
        <span class="ml-auto">Version 0.0.1</span>
    </footer>
    

    <script src="js/app.js"></script>
    <script src="js/plantilla.js"></script>
</body>

</html>
