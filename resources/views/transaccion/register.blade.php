@extends('transaccion.contenido')

@section('register')
<div class="row justify-content-center" data-template-screen="register" style="background:#c6c6c6;">
      <div class="col-md-12">
        
      </div>  
      <div class="col-md-12">        
        <div class="card-group mb-0">
        
          <div class="card p-4" style="background:#d5d5d5;">
          <img src="img/banner_ancho.jpeg" />
          <form class="form-horizontal was-validated" method="POST" enctype="multipart/form-data" action="{{ route('register')}}">
          {{ csrf_field() }}
              <div class="card-body text-center">
              <h1>Registro</h1>
              <p class="text-muted">Centro de Cobros en Línea.
              </p>
            
            @if(session()->has('message'))
                <div class="col-md-12">
                    <div class="alert alert-success" role="alert">
                    {{ session()->get('message') }}
                    </div>
               </div>
             @endif
             @if(session()->has('error'))
                <div class="col-md-12">
                    <div class="alert alert-danger" role="alert">
                    {{ session()->get('error') }}
                    </div>
               </div>
             @endif
              <div class="form-group row mb-4{{$errors->has('PaymentTypes' ? 'is-invalid' : '')}}">
                  <label class="col-md-3 form-control-label" for="text-input">Método de Pago *</label>
                  <div class="col-md-6">
                      <select class="form-control" name="PaymentTypes">
                          <option value="0" selected disabled>Seleccione</option>
                          <option value="401">Tarjet Crédito/Débito</option>
                          <option value="1002">American Express</option>
                      </select>    
                      {!!$errors->first('PaymentTypes','<span class="invalid-feedback">:message</span>')!!}
                  </div>
              </div>                               
              <div class="form-group row mb-4{{$errors->has('Description' ? 'is-invalid' : '')}}">
                  <label class="col-md-3 form-control-label" for="text-input">Descripción *</label>
                  <div class="col-md-6">
                      <input type="text" value="{{old('Description')}}" name="Description" id="Description" class="form-control" placeholder="Description">
                      {!!$errors->first('Description','<span class="invalid-feedback">:message</span>')!!}
                  </div>
              </div>                          
              <div class="form-group row mb-4{{$errors->has('Amount' ? 'is-invalid' : '')}}">
                  <label class="col-md-3 form-control-label" for="text-input">Monto *</label>
                  <div class="col-md-6">
                      <input type="number" value="{{old('Amount')}}" name="Amount" id="Amount" class="form-control" placeholder="0">
                      {!!$errors->first('Amount','<span class="invalid-feedback">:message</span>')!!}
                  </div>
              </div>                                       
              <div class="form-group row mb-4{{$errors->has('Reference' ? 'is-invalid' : '')}}">
                  <label class="col-md-3 form-control-label" for="text-input">Referencia *</label>
                  <div class="col-md-6">
                      <input type="text" value="{{old('Reference')}}" name="Reference" id="Reference" class="form-control" placeholder="Reference">
                      {!!$errors->first('Reference','<span class="invalid-feedback">:message</span>')!!}                      
                  </div>
              </div>
              <div class="form-group row mb-4{{$errors->has('ClientReference' ? 'is-invalid' : '')}}">
                  <label class="col-md-3 form-control-label" for="text-input">Referencia Cliente *</label>
                  <div class="col-md-6">
                      <input type="text" value="{{old('ClientReference')}}" name="ClientReference" id="ClientReference" class="form-control" placeholder="Client Reference">
                      {!!$errors->first('ClientReference','<span class="invalid-feedback">:message</span>')!!}                      
                  </div>
              </div>
              <div class="form-group row mb-4{{$errors->has('ExpirationDate' ? 'is-invalid' : '')}}">
                  <label class="col-md-3 form-control-label" for="text-input">Fecha de expiración *</label>
                  <div class="col-md-6">
                      <input type="date" value="{{old('ExpirationDate')}}" name="ExpirationDate" id="ExpirationDate" class="form-control" placeholder="yyy-MM-dd">
                      {!!$errors->first('ExpirationDate','<span class="invalid-feedback">:message</span>')!!}                       
                  </div>
              </div>

              <div class="row justify-content-center" >
                <div class="col-3">
                  <button type="submit" class="btn btn-primary form-control ">REGISTRAR</button>
                </div>
              </div>



            </div>


          </form>
          </div>
        </div>
      </div>
      <div class="col-md-12" style="background:#fb9b5a;">
        <p align="center"><a style="color: #FFF;" title="SIT 2022" href="https://www.soportetech.com.mx">SIT 2022</a></p>
      </div>
  </div>
@endsection
