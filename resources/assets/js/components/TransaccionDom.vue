<template>
            <main class="main">
            <!-- Breadcrumb -->
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="/">Escritorio</a></li>
            </ol>
            <div class="container-fluid">
                <div class="loader" v-if="loading"></div>
                <!-- Ejemplo de tabla Listado -->
                <div class="card">
                    <div class="card-header">
                        <i class="fa fa-align-justify"></i> Transacciones Domiciliación
                        <button type="button" @click="abrirModal('transaccionDom','registrar')" class="btn btn-secondary">
                            <i class="fa fa-plus-circle"></i>&nbsp;Nuevo
                        </button> &nbsp;
                        <button type="button" @click="descargarExportar()" class="btn btn-success btn-sm">
                            <i class="fa fa-cloud-download"></i>&nbsp;Exportar
                        </button> &nbsp;
                    </div>
                    <div class="card-body">
                        <div class="form-group row cdc-list-toolbar">
                            <div class="col-xl-6 col-lg-8 col-md-10 col-sm-12">
                                <div class="input-group">
                                    <select class="form-control col-lg-3 col-md-3 col-sm-4" v-model="criterio">
                                        <option value="ClientReference">Referencia</option>
                                      <option value="Reference">Ref. Transacción</option>                                      
                                      <option value="cliente_nombre">Cliente</option>
                                    </select>
                                    <input type="text" v-model="buscar" @keyup.enter="listarTransaccionDom(1,buscar,criterio)" class="form-control" placeholder="Texto a buscar">
                                    <button type="submit" @click="listarTransaccionDom(1,buscar,criterio)" class="btn btn-primary"><i class="fa fa-search"></i> Buscar</button>
                                </div>
                            </div>
                        </div>
                        <div class="cdc-table-shell">
                        <table class="table table-bordered table-striped table-sm cdc-responsive-table">
                            <thead>
                                <tr>
                                    <th class="text-center">Opciones
                                        <select v-model="offset" @change="listarTransaccionDom(1,buscar,criterio)">
                                            <option value="10" selected>10</option>
                                            <option value="25">25</option>
                                            <option value="50">50</option>
                                            <option value="100">100</option>
                                        </select>
                                    </th>
                                    <th class="text-center">Folio</th>
                                    <th class="text-center">Fecha</th>
                                    <th class="text-center">Referencia</th>
                                    <th class="text-center">Cliente</th>
                                    <th class="text-center">FolioC Pagos</th>
                                    <th class="text-center">Auth</th>
                                    <th class="text-center">Monto</th>                                    
                                    <th class="text-center">Time</th>
                                    <th class="text-center">Date</th>
                                    <th class="text-center">NB Company</th>
                                    <th class="text-center">Code</th>
                                    <th class="text-center">Message</th>
                                    <th class="text-center">Status
                                        <select v-model="filtroStatus" @change="listarTransaccionDom(1,buscar,criterio)">
                                            <option value="99" selected>Todos</option>
                                            <option value="approved">Aprobado</option>
                                            <option value="denied">Denegado</option>
                                            <option value="error">Error</option>
                                        </select>
                                    </th>
                                    <th class="text-center">Productivo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="transaccionDom in arrayTransaccionDom" :key="transaccionDom.id">
                                    <td class="text-center">
                                        <button type="button" @click="abrirModal('transaccionDom','ver',transaccionDom)" class="btn btn-success btn-sm cdc-action-button" title="Ver transacción domiciliación" aria-label="Ver transacción domiciliación">
                                          <i class="fa fa-eye"></i>
                                        </button> &nbsp;
                                        <!--<button type="button" class="btn btn-danger btn-sm" @click="eliminarTransaccionDom(transaccionDom.id)">
                                            <i class="fa fa-trash"></i>
                                        </button>-->
                                    </td>
                                    <td v-text="transaccionDom.id" class="text-center"></td>
                                    <td class="text-center">
                                        <span class="cdc-date-stack">
                                            <span>{{ $formatDateMx(transaccionDom.fecha) }}</span>
                                            <span class="cdc-date-stack__time">{{ $formatTimeMx(transaccionDom.fecha) }}</span>
                                        </span>
                                    </td>
                                    <td v-text="transaccionDom.response_reference" class="text-center"></td>
                                    <td v-text="transaccionDom.razon_social" class="text-center"></td>
                                    <td v-text="transaccionDom.foliocpagos" class="text-center"></td>
                                    <td v-text="transaccionDom.auth" class="text-center"></td>
                                    <td class="text-center">{{ $formatCurrency(transaccionDom.Amount / 100) }}</td>
                                    <td v-text="transaccionDom.time" class="text-center"></td>                       
                                    <td class="text-center">{{ $formatDateMx(transaccionDom.date) }}</td>
                                    <td v-text="transaccionDom.nb_company" class="text-center"></td>
                                    <td v-text="transaccionDom.code" class="text-center"></td>
                                    <td v-text="transaccionDom.message" class="text-center"></td>
                                    <td v-text="transaccionDom.status" class="text-center"></td>
                                    <td>
                                        <div v-if="transaccionDom.productivo==1">
                                            <span class="badge badge-success">Si</span>
                                        </div>
                                        <div v-else>
                                            <span class="badge badge-warning">No</span>
                                        </div>
                                    </td>
                                </tr>                                
                            </tbody>
                        </table>
                        </div>
                        <nav>
                            <ul class="pagination cdc-pagination">
                                <li class="page-item" v-if="pagination.current_page > 1">
                                    <a class="page-link" href="#" @click.prevent="cambiarPagina(pagination.current_page - 1,buscar,criterio)">Ant</a>
                                </li>
                                <li class="page-item" v-for="page in pagesNumber" :key="page" :class="[page == isActived ? 'active' : '']">
                                    <a class="page-link" href="#" @click.prevent="cambiarPagina(page,buscar,criterio)" v-text="page"></a>
                                </li>
                                <li class="page-item" v-if="pagination.current_page < pagination.last_page">
                                    <a class="page-link" href="#" @click.prevent="cambiarPagina(pagination.current_page + 1,buscar,criterio)">Sig</a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                </div>
                <!-- Fin ejemplo de tabla Listado -->
            </div>
            <!--Inicio del modal agregar/actualizar-->
            <div class="modal fade" tabindex="-1" :class="{'mostrar' : modal}" role="dialog" aria-labelledby="myModalLabel" style="overflow-y: scroll;display: none;" aria-hidden="true">
            <fieldset :disabled="loading">
                <div class="modal-dialog modal-primary modal-lg" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h4 class="modal-title" v-text="tituloModal"></h4>
                            <button type="button" class="close" @click="cerrarModal()" aria-label="Close">
                              <span aria-hidden="true">×</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <form action="" method="post" enctype="multipart/form-data" class="form-horizontal">                                
                                <template v-if="tipoAccion==2 || tipoAccion==3">
                                    <div class="form-group row">
                                        <label class="col-md-3 form-control-label" for="text-input">Referencia</label>
                                        <div class="col-md-9">
                                            <input type="text" v-model="response_reference" class="form-control" placeholder="" :readonly="tipoAccion == 3">                                        
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label class="col-md-3 form-control-label" for="text-input">Status</label>
                                        <div class="col-md-9">
                                            <input type="text" v-model="status" class="form-control" placeholder="" :readonly="tipoAccion == 3">                                        
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label class="col-md-3 form-control-label" for="text-input">FolioC Pagos</label>
                                        <div class="col-md-9">
                                            <input type="text" v-model="foliocpagos" class="form-control" placeholder="" :readonly="tipoAccion == 3">                                        
                                        </div>
                                    </div> 
                                    <div class="form-group row">
                                        <label class="col-md-3 form-control-label" for="text-input">Auth</label>
                                        <div class="col-md-9">
                                            <input type="text" v-model="auth" class="form-control" placeholder="" :readonly="tipoAccion == 3">                                        
                                        </div>
                                    </div>  
                                    <div class="form-group row">
                                        <label class="col-md-3 form-control-label" for="text-input">CD Response</label>
                                        <div class="col-md-9">
                                            <input type="text" v-model="cd_response" class="form-control" placeholder="" :readonly="tipoAccion == 3">                                        
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label class="col-md-3 form-control-label" for="text-input">CD Error</label>
                                        <div class="col-md-9">
                                            <input type="text" v-model="cd_error" class="form-control" placeholder="" :readonly="tipoAccion == 3">                                        
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label class="col-md-3 form-control-label" for="text-input">NB Error</label>
                                        <div class="col-md-9">
                                            <input type="text" v-model="nb_error" class="form-control" placeholder="" :readonly="tipoAccion == 3">                                        
                                        </div>
                                    </div>                                
                                    <div class="form-group row">
                                        <label class="col-md-3 form-control-label" for="text-input">Time</label>
                                        <div class="col-md-9">
                                            <input type="text" v-model="time" class="form-control" placeholder="" :readonly="tipoAccion == 3">                                        
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label class="col-md-3 form-control-label" for="text-input">Date</label>
                                        <div class="col-md-9">
                                            <input type="text" v-model="date" class="form-control" placeholder="" :readonly="tipoAccion == 3">                                        
                                        </div>
                                    </div>                                
                                    <div class="form-group row">
                                        <label class="col-md-3 form-control-label" for="text-input">NB Company</label>
                                        <div class="col-md-9">
                                            <input type="text" v-model="nb_company" class="form-control" placeholder="" :readonly="tipoAccion == 3">                                        
                                        </div>
                                    </div> 
                                    <div class="form-group row">
                                        <label class="col-md-3 form-control-label" for="text-input">NB Merchant</label>
                                        <div class="col-md-9">
                                            <input type="text" v-model="nb_merchant" class="form-control" placeholder="" :readonly="tipoAccion == 3">                                        
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label class="col-md-3 form-control-label" for="text-input">NB Street</label>
                                        <div class="col-md-9">
                                            <input type="text" v-model="nb_street" class="form-control" placeholder="" :readonly="tipoAccion == 3">                                        
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label class="col-md-3 form-control-label" for="text-input">CC Type</label>
                                        <div class="col-md-9">
                                            <input type="text" v-model="cc_type" class="form-control" placeholder="" :readonly="tipoAccion == 3">                                        
                                        </div>
                                    </div>                                             
                                    <div class="form-group row">
                                        <label class="col-md-3 form-control-label" for="text-input">TP Operation</label>
                                        <div class="col-md-9">
                                            <input type="text" v-model="tp_operation" class="form-control" placeholder="" :readonly="tipoAccion == 3">                                        
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label class="col-md-3 form-control-label" for="text-input">CC Name</label>
                                        <div class="col-md-9">
                                            <input type="text" v-model="cc_name" class="form-control" placeholder="" :readonly="tipoAccion == 3">                                        
                                        </div>
                                    </div> 
                                    <div class="form-group row">
                                        <label class="col-md-3 form-control-label" for="text-input">CC Number</label>
                                        <div class="col-md-9">
                                            <input type="text" v-model="cc_number" class="form-control" placeholder="" :readonly="tipoAccion == 3">                                        
                                        </div>
                                    </div> 
                                    <div class="form-group row">
                                        <label class="col-md-3 form-control-label" for="text-input">CC Exp Month</label>
                                        <div class="col-md-9">
                                            <input type="text" v-model="cc_expmonth" class="form-control" placeholder="" :readonly="tipoAccion == 3">                                        
                                        </div>
                                    </div> 
                                    <div class="form-group row">
                                        <label class="col-md-3 form-control-label" for="text-input">CC Exp Year</label>
                                        <div class="col-md-9">
                                            <input type="text" v-model="cc_expyear" class="form-control" placeholder="" :readonly="tipoAccion == 3">                                        
                                        </div>
                                    </div>                                     
                                    <div class="form-group row">
                                        <label class="col-md-3 form-control-label" for="text-input">Amount</label>
                                        <div class="col-md-9">
                                            <input type="text" v-model="response_amount" class="form-control" placeholder="" :readonly="tipoAccion == 3">                                        
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label class="col-md-3 form-control-label" for="text-input">Voucher</label>
                                        <div class="col-md-9">
                                            <input type="text" v-model="voucher" class="form-control" placeholder="" :readonly="tipoAccion == 3">
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label class="col-md-3 form-control-label" for="text-input">Payment Type</label>
                                        <div class="col-md-9">
                                            <input type="text" v-model="payment_type" class="form-control" placeholder="" :readonly="tipoAccion == 3">                                        
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label class="col-md-3 form-control-label" for="text-input">TOKEN</label>
                                        <div class="col-md-9">
                                            <input type="text" v-model="response_token" class="form-control" placeholder="" :readonly="tipoAccion == 3">
                                        </div>
                                    </div>
                                </template>
                                <template v-if="tipoAccion==1">
                                    <div class="form-group row">
                                        <label class="col-md-3 form-control-label" for="text-input">Transacción</label>
                                        <div class="col-md-9">
                                            <select class="form-control" v-model="idtransaccion">
                                                <option value="0">Seleccione</option>
                                                <option v-for="transaccion in arrayTransaccion" :key="transaccion.id" :value="transaccion.id" v-text="transaccion.ClientReference"></option>
                                            </select>
                                        </div>
                                    </div>                                    
                                </template>
                                <div v-show="errorTransaccionDom" class="form-group row div-error">
                                    <div class="text-center text-error">
                                        <div v-for="error in errorMostrarMsjTransaccionDom" :key="error" v-text="error">
                                        </div>
                                    </div>
                                </div>
                            
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" @click="cerrarModal()">Cerrar</button>
                            <button type="button" v-if="tipoAccion==1" class="btn btn-primary" @click="registrarTransaccionDom()">Cargar</button>
                            <button type="button" v-if="tipoAccion==2" class="btn btn-primary" @click="actualizarTransaccionDom()">Actualizar</button>
                        </div>
                    </div>
                    <!-- /.modal-content -->
                </div>
                <!-- /.modal-dialog -->
            </fieldset>
            </div>
            <!--Fin del modal-->
        </main>
</template>
<script>
    
    export default {
        props: ['tipo','productivo'],
        data (){
            return {                
                transacciondom_id: 0,
                idtransaccion : 0,
                fecha : null,
                response_reference : '',
                status : '',
                foliocpagos : '',
                auth : '',
                cd_response : '',
                cd_error : '',
                nb_error: '',
                time : '',
                date : '',
                nb_company : '',
                nb_merchant : '',
                nb_street : '',
                cc_type : '',
                tp_operation : '',
                cc_name : '',
                cc_number : '',
                cc_expmonth : '',
                cc_expyear : '',
                response_amount : '',
                voucher : '',
                payment_type : '',
                response_token : '',
                arrayTransaccionDom : [],
                arrayTransaccion : [],
                modal : 0,
                tituloModal : '',
                tipoAccion : 0,
                errorTransaccionDom : 0,
                errorMostrarMsjTransaccionDom : [],
                pagination : {
                    'total' : 0,
                    'current_page' : 0,
                    'per_page' : 0,
                    'last_page' : 0,
                    'from' : 0,
                    'to' : 0,
                },
                offset : 10,
                filtroStatus : 99,
                criterio : 'Reference',
                buscar : '',
                loading: false
            }
        },
        computed:{
            isActived: function(){
                return this.pagination.current_page;
            },
            pagesNumber: function() {
                return this.$paginationPages(this.pagination);
            }
        },
        methods : {
            listarTransaccionDom (page,buscar,criterio){
                let me=this;
                var url= '/transaccionDom?page=' + page + '&buscar='+ encodeURIComponent(buscar || '') + '&criterio='+ encodeURIComponent(criterio || 'Reference') + '&offset='+ me.offset + '&status='+ me.filtroStatus;
                axios.get(url).then(function (response) {
                    var transaccionDom= response.data;
                    me.arrayTransaccionDom = transaccionDom.transaccionesDom.data;
                    me.pagination= transaccionDom.pagination;
                })
                .catch(function (error) {
                    console.log(error);
                });
            },
            descargarExportar (){
                let me = this;

                axios({
                    url: '/transaccionDom/exportar',
                    meth: 'GET',
                    responseType: 'blob'
                    }).then(function (response) {                    
                        var fileURL = window.URL.createObjectURL(new Blob([response.data]));
                        var fileLink = document.createElement('a');
                        
                        fileLink.href = fileURL;
                        fileLink.setAttribute('download', 'transacciones_domiciliadas.xls');
                        document.body.appendChild(fileLink);
                        
                        fileLink.click();
                        fileLink.remove();
                }).catch(function (error) {
                    console.log(error);
                        swal(
                        'Error!',
                        'Error al descargar el archivo.',
                        'error'
                        )                      
                }); 
            },
            cambiarPagina(page,buscar,criterio){
                let me = this;
                //Actualiza la página actual
                me.pagination.current_page = page;
                //Envia la petición para visualizar la data de esa página
                me.listarTransaccionDom(page,buscar,criterio);
            },
            selectTransaccion(){
                let me=this;
                var url= '/transaccion/selectDomiciliacion';
                axios.get(url).then(function (response) {
                    //console.log(response);
                    var respuesta= response.data;
                    me.arrayTransaccion = respuesta.transacciones;
                })
                .catch(function (error) {
                    console.log(error);
                });
            },                        
            registrarTransaccionDom(){                
                if (this.validarTransaccionDom()){                    
                    return;
                }
                this.loading =  true;
                let me = this;

                axios.post('/transaccionDom/registrar',{                    
                    'idtransaccion': me.idtransaccion
                }).then(function (response) {

                    var respuesta = response.data;
                    var error = respuesta.error;
                    var mensaje = respuesta.msg;

                    if(error == ''){
                        swal(
                        'Registro exitoso!',
                        mensaje,
                        'success'
                        ) 
                    } else {
                        swal(
                        'Error!',
                        'Error al realizar el registro. Error: ' + error,
                        'error'
                        )
                    }
                    
                    console.log("Se guardo.");

                    me.cerrarModal();
                    me.listarTransaccionDom(1,'','Reference');
                }).catch(function (error) {
                    console.log(error);
                        swal(
                        'Error!',
                        'Error al actualizar el registro.',
                        'error'
                        )                    
                }).finally(() => {
                        this.loading =  false;
                });                 
            },
            actualizarTransaccionDom(){                
               if (this.validarTransaccionDom()){                    
                    return;
                }
                this.loading =  true;
                let me = this;

                axios.put('/transaccionDom/actualizar',{                                        
                    'response_reference': this.response_reference,
                    'status': this.status,
                    'foliocpagos': this.foliocpagos,
                    'auth': this.auth,
                    'cd_response': this.cd_response,
                    'cd_error': this.cd_error,
                    'nb_error': this.nb_error,
                    'time': this.time,
                    'date': this.date,
                    'nb_company': this.nb_company,
                    'nb_merchant': this.nb_merchant,
                    'nb_street' : this.nb_street,
                    'cc_type': this.cc_type,
                    'tp_operation': this.tp_operation,
                    'cc_name' : this.cc_name,
                    'cc_number' : this.cc_number,
                    'cc_expmonth' : this.cc_expmonth,
                    'cc_expyear' : this.cc_expyear,
                    'response_amount' : this.response_amount,
                    'voucher' : this.voucher,
                    'payment_type' : this.payment_type,
                    'response_token' : this.response_token,
                    'id': this.transacciondom_id
                }).then(function (response) {
                    me.cerrarModal();
                    me.listarTransaccionDom(1,'','Reference');
                }).catch(function (error) {
                    console.log(error);
                        swal(
                        'Error!',
                        'Error al actualizar el registro.',
                        'error'
                        )                    
                }).finally(() => {
                        this.loading =  false;
                });       
            },            
            eliminarTransaccionDom(id){              
                let me = this;
                swal({
                title: 'Esta seguro de eliminar este Cargo Domiciliado?',
                type: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Aceptar!',
                cancelButtonText: 'Cancelar',
                confirmButtonClass: 'btn btn-success',
                cancelButtonClass: 'btn btn-danger',
                buttonsStyling: false,
                reverseButtons: true
                }).then((result) => {
                    axios.put('/transaccionDom/eliminar',{
                        'id': id
                    }).then(function (response) {        
                        me.listarTransaccionDom(1,'','Reference');
                    }).catch(function (error) {
                        console.log(error);
                        swal(
                        'Error!',
                        'Error al eliminar el Cargo Domiciliado.',
                        'error'
                        )
                    });
                })
            },     
            validarTransaccionDom(){
                this.errorTransaccionDom=0;
                this.errorMostrarMsjTransaccionDom =[];

                if(this.tipoAccion==1){
                    if(this.idtransaccion==0) this.errorMostrarMsjTransaccionDom.push("Debe seleccionar una transacción para realizar el cargo.");
                }
                                
                if(this.tipoAccion==2){
                    if (!this.response_reference) this.errorMostrarMsjTransaccionDom.push("El response_reference no puede estar vacío.");
                    if (!this.status) this.errorMostrarMsjTransaccionDom.push("El status no puede estar vacío."); 
                    if (!this.foliocpagos) this.errorMostrarMsjTransaccionDom.push("El foliocpagos no puede estar vacío.");                                
                    if (!this.cd_response) this.errorMostrarMsjTransaccionDom.push("El cd_response no puede estar vacío.");
                    if (!this.cd_error) this.errorMostrarMsjTransaccionDom.push("El cd_error no puede estar vacía.");
                    if (!this.nb_error) this.errorMostrarMsjTransaccionDom.push("El nb_error no puede estar vacío.");
                    if (!this.time) this.errorMostrarMsjTransaccionDom.push("El time no puede estar vacía.");
                    if (!this.date) this.errorMostrarMsjTransaccionDom.push("El date no puede estar vacío.");
                    if (!this.nb_company) this.errorMostrarMsjTransaccionDom.push("La nb_company no puede estar vacía.");
                    if (!this.nb_merchant) this.errorMostrarMsjTransaccionDom.push("El nb_merchant no puede estar vacío.");            
                    if (!this.cc_type) this.errorMostrarMsjTransaccionDom.push("El cc_type no puede estar vacío.");
                    if (!this.tp_operation) this.errorMostrarMsjTransaccionDom.push("El tp_operation no puede estar vacío.");                    
                }                

                if (this.errorMostrarMsjTransaccionDom.length) this.errorTransaccionDom = 1;

                return this.errorTransaccionDom;
            },                  
            cerrarModal(){
                this.modal=0;
                this.tituloModal='';
                
                this.fecha=null;
                this.idtransaccion=0;
                this.response_reference='';
                this.status='';
                this.foliocpagos='';
                this.auth='';                             
                this.cd_response='';
                this.cd_error='';
                this.nb_error='';
                this.time='';
                this.date='';
                this.nb_company='';
                this.nb_merchant='';
                this.nb_street='',
                this.cc_type='';
                this.tp_operation='';
                this.cc_name='',
                this.cc_number='',
                this.cc_expmonth='',
                this.cc_expyear='',                
                this.response_amount='',
                this.voucher='',
                this.payment_type='',
                this.response_token='',
                this.arrayTransaccion=[];
            },
            abrirModal(modelo, accion, data = []){
                switch(modelo){
                    case "transaccionDom":
                    {
                        switch(accion){
                            case 'registrar':
                            {
                                this.modal = 1;                                
                                this.tituloModal = 'Registrar Cargo Domiciliación';
                                this.idtransaccion=0;
                                this.idcliente=0;
                                this.response_reference='';
                                this.status='';
                                this.foliocpagos='';
                                this.auth='';                                
                                this.cd_response='';
                                this.cd_error='';
                                this.nb_error='';
                                this.time='';
                                this.date='';
                                this.nb_company='';
                                this.nb_merchant='';
                                this.nb_street='',
                                this.cc_type='';
                                this.tp_operation='';
                                this.cc_name='',
                                this.cc_number='',
                                this.cc_expmonth='',
                                this.cc_expyear='',                                
                                this.response_amount='',
                                this.voucher='',
                                this.payment_type='',
                                this.response_token=''
                                this.tipoAccion = 1;
                                this.selectTransaccion();
                                break;
                            }
                            case 'actualizar':
                            {                                
                                this.modal=1;
                                this.tituloModal='Actualizar Cargo Domiciliación';                                
                                this.transacciondom_id=data['id'];
                                this.idcliente=data['idcliente'];
                                this.response_reference = data['response_reference'];
                                this.status = data['status'];
                                this.foliocpagos = data['foliocpagos'];
                                this.auth = data['auth'];                                                       
                                this.cd_response = data['cd_response'];
                                this.cd_error = data['cd_error'];
                                this.nb_error = data['nb_error'];
                                this.time = data['time'];
                                this.date = data['date'];
                                this.nb_company = data['nb_company'];
                                this.nb_merchant = data['nb_merchant'];
                                this.nb_street=data['nb_street'];
                                this.cc_type = data['cc_type'];
                                this.tp_operation = data['tp_operation'];
                                this.cc_name=data['cc_name'];
                                this.cc_number=data['cc_number'];
                                this.cc_expmonth=data['cc_expmonth'];
                                this.cc_expyear=data['cc_expyear'];
                                this.response_amount=data['response_amount'];
                                this.voucher=data['voucher'];
                                this.payment_type=data['payment_type'];
                                this.response_token=data['response_token'];
                                this.tipoAccion = 2;
                                window.scrollTo(0,0);
                                break;
                            } 
                            case 'ver':
                            {                                
                                this.modal=1;
                                this.tituloModal='Ver Cargo Domiciliación';                                
                                this.transacciondom_id=data['id'];
                                this.idcliente=data['idcliente'];
                                this.response_reference = data['response_reference'];
                                this.status = data['status'];
                                this.foliocpagos = data['foliocpagos'];
                                this.auth = data['auth'];                                                       
                                this.cd_response = data['cd_response'];
                                this.cd_error = data['cd_error'];
                                this.nb_error = data['nb_error'];
                                this.time = data['time'];
                                this.date = data['date'];
                                this.nb_company = data['nb_company'];
                                this.nb_merchant = data['nb_merchant'];
                                this.nb_street=data['nb_street'];
                                this.cc_type = data['cc_type'];
                                this.tp_operation = data['tp_operation'];
                                this.cc_name=data['cc_name'];
                                this.cc_number=data['cc_number'];
                                this.cc_expmonth=data['cc_expmonth'];
                                this.cc_expyear=data['cc_expyear'];                                
                                this.response_amount=data['response_amount'];
                                this.voucher=data['voucher'];
                                this.payment_type=data['payment_type'];
                                this.response_token=data['response_token'];
                                this.tipoAccion = 3;
                                window.scrollTo(0,0);
                                break;
                            }                                           
                        }
                    }
                }
            }            
        },
        mounted() {
            this.listarTransaccionDom(1,this.buscar,this.criterio);
        }
    }
</script>
<style>    
    .modal-content{
        width: 100% !important;
        position: absolute !important;
    }
    .mostrar{
        display: list-item !important;
        opacity: 1 !important;
        position: absolute !important;
        background-color: #3c29297a !important;
    }
    .div-error{
        display: flex;
        justify-content: center;
    }
    .text-error{
        color: red !important;
        font-weight: bold;
    }
</style>
