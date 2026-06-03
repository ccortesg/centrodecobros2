@extends('transaccion.contenido')

@section('register')
<div class="row justify-content-center" data-template-screen="url" style="background:#c6c6c6;">
      
      <div class="col-md-12">        
        <div class="card-group mb-0">
        
          <div class="card p-4" style="background:#d5d5d5;">
          <img src="img/banner_ancho.jpeg" />
          <form class="form-horizontal was-validated" method="POST" enctype="multipart/form-data" action="{{ route('open')}}">
          {{ csrf_field() }}
              <div class="card-body text-center">
              <h1>Registro</h1>
              <p class="text-muted">Centro de Cobros en Línea.
              </p>                                          
              <div class="form-group row mb-4{{$errors->has('url' ? 'is-invalid' : '')}}">
                  <label class="col-md-3 form-control-label" for="text-input">URL *</label>
                  <div class="col-md-6">
                      <input type="text" value="{{old('url')}}" name="url" id="url" class="form-control" placeholder="url">
                      {!!$errors->first('url','<span class="invalid-feedback">:message</span>')!!}                      
                  </div>
              </div>              

              <div class="row justify-content-center" >
                <div class="col-3">
                  <button type="submit" class="btn btn-primary form-control ">ABRIR</button>
                </div>
              </div>
            </div>
          </form>
          </div>
        </div>
      </div>
      <div class="col-md-12">              
        @if(session()->has('message'))
                  <div class="col-md-12">                    
                  <iframe src="{{ session()->get('message') }}" sandbox></iframe>
                  </div>
        @endif
      </div>  
      <div class="col-md-12" style="background:#fb9b5a;">
        <p align="center"><a style="color: #FFF;" title="SIT 2022" href="https://www.soportetech.com.mx">SIT 2022</a></p>
      </div>
  </div>
@endsection
