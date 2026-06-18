    @extends('principal')
    @section('contenido')

    @if(Auth::check())
            @if (Auth::user()->idrol == 1 || Auth::user()->idrol == 2)
            <template v-if="menu==0">
                <dashboard></dashboard>
            </template>

            <template v-if="menu==1">
                <transaccion :tipo="1" :productivo="{{Auth::user()->productivo}}" :idrol="{{Auth::user()->idrol}}"></transaccion>
            </template>

            <template v-if="menu==2">
                <respuesta :tipo="1"></respuesta>
            </template>

            <template v-if="menu==3">
                <user></user>
            </template>

            <template v-if="menu==4">
                <rol></rol>
            </template>

            <template v-if="menu==5">
                <h1>Ayuda</h1>
            </template>

            <template v-if="menu==6">
                <h1>Acerca de</h1>
            </template>	 
            
            <template v-if="menu==7">
                <estado></estado>
            </template>	  

            <template v-if="menu==8">
                <ciudad></ciudad>
            </template>	  

            <template v-if="menu==9">
                <cliente></cliente>
            </template>

            @if (Auth::user()->idrol == 1)
            <template v-if="menu==10">
                <clienteconsolidar></clienteconsolidar>
            </template>
            <template v-if="menu==28">
                <clientedepurar></clientedepurar>
            </template>
            @endif

            <template v-if="menu==11">
                <transaccion :tipo="2" :productivo="{{Auth::user()->productivo}}" :idrol="{{Auth::user()->idrol}}"></transaccion>
            </template>

            <template v-if="menu==29">
                <domiciliacionactiva></domiciliacionactiva>
            </template>

            <template v-if="menu==12">
                <respuesta :tipo="2"></respuesta>
            </template>

            <template v-if="menu==13">
                <transacciondom :productivo="{{Auth::user()->productivo}}" :idrol="{{Auth::user()->idrol}}"></transacciondom>
            </template>

            <template v-if="menu==14">
                <transaccion :tipo="3" :productivo="{{Auth::user()->productivo}}" :idrol="{{Auth::user()->idrol}}"></transaccion>
            </template>

            <template v-if="menu==15">
                <respuesta :tipo="3"></respuesta>
            </template>

            <template v-if="menu==18">
                <reporteligas :tipo="1"></reporteligas>
            </template>

            <template v-if="menu==19">
                <reporteligasdom :tipo="2"></reporteligasdom>
            </template>

            <template v-if="menu==20">
                <reportespei :tipo="3"></reportespei>
            </template>

            <template v-if="menu==21">
                <reporteligas :tipo="4"></reporteligas>
            </template>

            <template v-if="menu==22">
                <consultaspei></consultaspei>
            </template>

            <template v-if="menu==23">
                <pagospei></pagospei>
            </template>

            <template v-if="menu==24">
                <cancelaspei></cancelaspei>
            </template>

            <template v-if="menu==25">
                <reportecargosrecurrentes></reportecargosrecurrentes>
            </template>

            <template v-if="menu==26">
                <transaccion :tipo="4" :productivo="{{Auth::user()->productivo}}" :idrol="{{Auth::user()->idrol}}"></transaccion>
            </template>

            <template v-if="menu==27">
                <respuesta :tipo="4"></respuesta>
            </template>

            <template v-if="menu==30">
                <pagorecibido></pagorecibido>
            </template>
            @else


            @endif

    @endif
       
        
    @endsection
