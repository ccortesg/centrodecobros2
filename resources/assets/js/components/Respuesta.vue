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
                        <i class="fa fa-align-justify"></i> Respuestas
                        <!--<button type="button" @click="abrirModal('respuesta','registrar')" class="btn btn-secondary">
                            <i class="icon-plus"></i>&nbsp;Nuevo
                        </button>--> &nbsp;
                        <button type="button" @click="descargarExportar()" class="btn btn-success btn-sm">
                            <i class="icon-cloud-download"></i>&nbsp;Exportar
                        </button> &nbsp;
                    </div>
                    <div class="card-body">
                        <div class="form-group row">
                            <div class="col-xl-6 col-lg-8 col-md-10 col-sm-12">
                                <div class="input-group">
                                    <select class="form-control col-lg-3 col-md-3 col-sm-4" v-model="criterio">
                                      <option value="ClientReference">Ref. Cliente</option>
                                      <option value="Reference">Ref. Transacción</option>
                                      <option value="responseReference">Ref. Respuesta</option>
                                      <option value="cliente_nombre">Cliente</option>
                                    </select>
                                    <input type="text" v-model="buscar" @keyup.enter="listarRespuesta(1,buscar,criterio)" class="form-control" placeholder="Texto a buscar">
                                    <button type="submit" @click="listarRespuesta(1,buscar,criterio)" class="btn btn-primary"><i class="fa fa-search"></i> Buscar</button>
                                </div>
                            </div>
                        </div>
                        <table class="table table-bordered table-striped table-sm table-responsive">
                            <thead>
                                <tr>
                                    <th class="text-center">Opciones
                                        <select v-model="offset" @change="listarRespuesta(1,buscar,criterio)">
                                            <option value="10" selected>10</option>
                                            <option value="25">25</option>
                                            <option value="50">50</option>
                                            <option value="100">100</option>
                                        </select>
                                    </th>
                                    <th class="text-center">Folio</th>
                                    <th class="text-center">Fecha</th>
                                    <th class="text-center">Cliente</th>
                                    <th class="text-center">Referencia</th>
                                    <th class="text-center">Ref. Transacción</th>
                                    <th class="text-center">Ref. Respuesta</th>
                                    <th class="text-center">FolioC Pagos</th>
                                    <th class="text-center">Auth</th>
                                    <th class="text-center">CD Response</th>
                                    <th class="text-center">Amount</th>
                                    <th class="text-center">NB Error</th>
                                    <th class="text-center">Time</th>
                                    <th class="text-center">Date</th>
                                    <th class="text-center">NB Company</th>
                                    <th class="text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="respuesta in arrayRespuesta" :key="respuesta.id">
                                    <td class="text-center">
                                        <button type="button" @click="abrirModal('respuesta','ver',respuesta)" class="btn btn-success btn-sm">
                                          <i class="icon-eye"></i>
                                        </button> &nbsp;
                                        <!--<button type="button" class="btn btn-danger btn-sm" @click="eliminarRespuesta(respuesta.id)">
                                            <i class="icon-trash"></i>
                                        </button>-->
                                    </td>
                                    <td v-text="respuesta.id" class="text-center"></td>
                                    <td v-text="respuesta.fecha" class="text-center"></td>
                                    <td v-text="respuesta.nombre_cliente" class="text-center"></td>
                                    <td v-text="respuesta.cliente_reference" class="text-center"></td>
                                    <td v-text="respuesta.transaccion_reference" class="text-center"></td>
                                    <td v-text="respuesta.reference" class="text-center"></td>
                                    <td v-text="respuesta.foliocpagos" class="text-center"></td>
                                    <td v-text="respuesta.auth" class="text-center"></td>
                                    <td v-text="respuesta.cd_response" class="text-center"></td>
                                    <td class="text-center">
                                        {{ $formatCurrency(respuesta.amount) }}
                                    </td>
                                    <td v-text="respuesta.nb_error" class="text-center"></td>
                                    <td v-text="respuesta.time" class="text-center"></td>                       
                                    <td v-text="respuesta.date" class="text-center"></td>
                                    <td v-text="respuesta.nb_company" class="text-center"></td>
                                    <td v-text="respuesta.status" class="text-center"></td>
                                </tr>                                
                            </tbody>
                        </table>
                        <nav>
                            <ul class="pagination">
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
                                <div class="form-group row">
                                    <label class="col-md-3 form-control-label" for="text-input">Ref. Transacción</label>
                                    <div class="col-md-9">
                                        <input type="text" v-model="reference" class="form-control" placeholder="" :readonly="tipoAccion == 3">                                        
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
                                        <input type="text" v-model="amount" class="form-control" placeholder="" :readonly="tipoAccion == 3">                                        
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label class="col-md-3 form-control-label" for="text-input">ID URL</label>
                                    <div class="col-md-9">
                                        <input type="text" v-model="id_url" class="form-control" placeholder="" :readonly="tipoAccion == 3">                                        
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label class="col-md-3 form-control-label" for="text-input">Email </label>
                                    <div class="col-md-9">
                                        <input type="text" v-model="email" class="form-control" placeholder="Email" :readonly="tipoAccion == 3">
                                        
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label class="col-md-3 form-control-label" for="text-input">Payment Type</label>
                                    <div class="col-md-9">
                                        <input type="text" v-model="payment_type" class="form-control" placeholder="" :readonly="tipoAccion == 3">                                        
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label class="col-md-3 form-control-label" for="text-input">Promoción</label>
                                    <div class="col-md-9">
                                        <input type="text" v-model="promocion" class="form-control" placeholder="" :readonly="tipoAccion == 3">                                        
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label class="col-md-3 form-control-label" for="text-input">Number TKN</label>
                                    <div class="col-md-9">
                                        <input type="text" v-model="number_tkn" class="form-control" placeholder="" :readonly="tipoAccion == 3">
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label class="col-md-3 form-control-label" for="text-input">CC Mask</label>
                                    <div class="col-md-9">
                                        <input type="text" v-model="cc_mask" class="form-control" placeholder="" :readonly="tipoAccion == 3">
                                    </div>
                                </div>
                                <div v-show="errorRespuesta" class="form-group row div-error">
                                    <div class="text-center text-error">
                                        <div v-for="error in errorMostrarMsjRespuesta" :key="error" v-text="error">
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" @click="cerrarModal()">Cerrar</button>
                            <button type="button" v-if="tipoAccion==1" class="btn btn-primary" @click="registrarRespuesta()">Guardar</button>
                            <button type="button" v-if="tipoAccion==2" class="btn btn-primary" @click="actualizarRespuesta()">Actualizar</button>
                        </div>
                    </div>
                    <!-- /.modal-content -->
                </div>
                <!-- /.modal-dialog -->
            </div>
            <!--Fin del modal-->
        </main>
</template>
<script>
    
    export default {
        props: ['tipo'],
        data (){
            return {                
                respuesta_id: 0,
                fecha : null,
                reference : '',
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
                cc_type : '',
                tp_operation : '',
                cc_name : '',
                cc_number : '',
                cc_expmonth : '',
                cc_expyear : '',
                amount : '',
                id_url : '',
                email : '',
                payment_type : '',
                promocion : '',
                number_tkn : '',
                cc_mask : '',
                arrayRespuesta : [],                
                modal : 0,
                tituloModal : '',
                tipoAccion : 0,
                errorRespuesta : 0,
                errorMostrarMsjRespuesta : [],
                pagination : {
                    'total' : 0,
                    'current_page' : 0,
                    'per_page' : 0,
                    'last_page' : 0,
                    'from' : 0,
                    'to' : 0,
                },
                offset : 10,
                criterio : 'Reference',
                buscar : '',
                loading: false
            }
        },
        computed:{
            isActived: function(){
                return this.pagination.current_page;
            },
            //Calcula los elementos de la paginación
            pagesNumber: function() {
                if(!this.pagination.to) {
                    return [];
                }
                
                var from = this.pagination.current_page - this.offset; 
                if(from < 1) {
                    from = 1;
                }

                var to = from + (this.offset * 2); 
                if(to >= this.pagination.last_page){
                    to = this.pagination.last_page;
                }  

                var pagesArray = [];
                while(from <= to) {
                    pagesArray.push(from);
                    from++;
                }
                return pagesArray;             

            }
        },
        methods : {
            listarRespuesta (page,buscar,criterio){
                let me=this;
                var url= '/respuesta?page=' + page + '&buscar='+ buscar + '&criterio='+ criterio + '&offset='+ me.offset + '&tipo='+ me.tipo;
                axios.get(url).then(function (response) {
                    var respuesta= response.data;
                    me.arrayRespuesta = respuesta.respuestas.data;
                    me.pagination= respuesta.pagination;
                })
                .catch(function (error) {
                    console.log(error);
                });
            },
            descargarExportar (){
                let me = this;

                axios({
                    url: '/respuesta/exportar?tipo='+ me.tipo,
                    meth: 'GET',
                    responseType: 'blob'
                    }).then(function (response) {                    
                        var fileURL = window.URL.createObjectURL(new Blob([response.data]));
                        var fileLink = document.createElement('a');
                        
                        fileLink.href = fileURL;
                        fileLink.setAttribute('download', 'respuestas.xls');
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
                me.listarRespuesta(page,buscar,criterio);
            },                        
            registrarRespuesta(){
                this.loading =  true;
                if (this.validarRespuesta()){
                    this.loading =  false;
                    return;
                }
                
                let me = this;

                axios.post('/respuesta/registrar',{                    
                    'fecha': this.fecha,
                    'reference': this.reference,
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
                    'cc_type': this.cc_type,
                    'tp_operation': this.tp_operation,
                    'cc_name' : this.cc_name,
                    'cc_number' : this.cc_number,
                    'cc_expmonth' : this.cc_expmonth,
                    'cc_expyear' : this.cc_expyear,
                    'amount' : this.amount,
                    'id_url' : this.id_url,
                    'payment_type' : this.payment_type,
                    'promocion' : this.promocion,
                    'number_tkn' : this.number_tkn,
                    'cc_mask' : this.cc_mask                    
                }).then(function (response) {
                    me.cerrarModal();
                    me.listarRespuesta(1,'','Reference');
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
            actualizarRespuesta(){
                this.loading =  true;
               if (this.validarRespuesta()){
                    this.loading =  false;
                    return;
                }
                
                let me = this;

                axios.put('/respuesta/actualizar',{                                        
                    'reference': this.reference,
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
                    'cc_type': this.cc_type,
                    'tp_operation': this.tp_operation,
                    'cc_name' : this.cc_name,
                    'cc_number' : this.cc_number,
                    'cc_expmonth' : this.cc_expmonth,
                    'cc_expyear' : this.cc_expyear,
                    'amount' : this.amount,
                    'id_url' : this.id_url,
                    'payment_type' : this.payment_type,
                    'promocion' : this.promocion,
                    'number_tkn' : this.number_tkn,
                    'cc_mask' : this.cc_mask,
                    'id': this.respuesta_id
                }).then(function (response) {
                    me.cerrarModal();
                    me.listarRespuesta(1,'','Reference');
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
            eliminarRespuesta(id){              
                let me = this;
                swal({
                title: 'Esta seguro de eliminar este respuesta?',
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
                    axios.put('/respuesta/eliminar',{
                        'id': id
                    }).then(function (response) {        
                        me.listarRespuesta(1,'','Reference');
                    }).catch(function (error) {
                        console.log(error);
                        swal(
                        'Error!',
                        'Error al eliminar el respuesta.',
                        'error'
                        )
                    });
                })
            },     
            validarRespuesta(){
                this.errorRespuesta=0;
                this.errorMostrarMsjRespuesta =[];
                
                
                if (!this.reference) this.errorMostrarMsjRespuesta.push("El reference no puede estar vacío.");
                if (!this.status) this.errorMostrarMsjRespuesta.push("El status no puede estar vacío."); 
                if (!this.foliocpagos) this.errorMostrarMsjRespuesta.push("El foliocpagos no puede estar vacío.");                                
                if (!this.cd_response) this.errorMostrarMsjRespuesta.push("El cd_response no puede estar vacío.");
                if (!this.cd_error) this.errorMostrarMsjRespuesta.push("El cd_error no puede estar vacía.");
                if (!this.nb_error) this.errorMostrarMsjRespuesta.push("El nb_error no puede estar vacío.");
                if (!this.time) this.errorMostrarMsjRespuesta.push("El time no puede estar vacía.");
                if (!this.date) this.errorMostrarMsjRespuesta.push("El date no puede estar vacío.");
                if (!this.nb_company) this.errorMostrarMsjRespuesta.push("La nb_company no puede estar vacía.");
                if (!this.nb_merchant) this.errorMostrarMsjRespuesta.push("El nb_merchant no puede estar vacío.");            
                if (!this.cc_type) this.errorMostrarMsjRespuesta.push("El cc_type no puede estar vacío.");
                if (!this.tp_operation) this.errorMostrarMsjRespuesta.push("El tp_operation no puede estar vacío.");
                if (!this.email) this.errorMostrarMsjRespuesta.push("El email no puede estar vacío.");

                if (this.errorMostrarMsjRespuesta.length) this.errorRespuesta = 1;

                return this.errorRespuesta;
            },                  
            cerrarModal(){
                this.modal=0;
                this.tituloModal='';
                
                this.fecha=null;
                this.reference='';
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
                this.cc_type='';
                this.tp_operation='';
                this.cc_name='',
                this.cc_number='',
                this.cc_expmonth='',
                this.cc_expyear='',
                this.amount='',
                this.id_url='',
                this.email='',
                this.payment_type='',
                this.promocion='',
                this.number_tkn='',
                this.cc_mask=''
            },
            abrirModal(modelo, accion, data = []){
                switch(modelo){
                    case "respuesta":
                    {
                        switch(accion){
                            case 'registrar':
                            {
                                this.modal = 1;                                
                                this.tituloModal = 'Registrar Respuesta';                                
                                this.reference='';
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
                                this.cc_type='';
                                this.tp_operation='';
                                this.cc_name='',
                                this.cc_number='',
                                this.cc_expmonth='',
                                this.cc_expyear='',
                                this.amount='',
                                this.id_url='',
                                this.email='',
                                this.payment_type='',
                                this.promocion='',
                                this.number_tkn='',
                                this.cc_mask=''
                                this.tipoAccion = 1;
                                break;
                            }
                            case 'actualizar':
                            {                                
                                this.modal=1;
                                this.tituloModal='Actualizar Respuesta';                                
                                this.respuesta_id=data['id'];
                                this.reference = data['reference'];
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
                                this.cc_type = data['cc_type'];
                                this.tp_operation = data['tp_operation'];
                                this.cc_name=data['cc_name'];
                                this.cc_number=data['cc_number'];
                                this.cc_expmonth=data['cc_expmonth'];
                                this.cc_expyear=data['cc_expyear'];
                                this.amount=data['amount'];
                                this.id_url=data['id_url'];
                                this.email=data['email'];
                                this.payment_type=data['payment_type'];
                                this.promocion=data['promocion'];
                                this.number_tkn=data['number_tkn'];
                                this.cc_mask=data['cc_mask'];
                                this.tipoAccion = 2;
                                window.scrollTo(0,0);
                                break;
                            } 
                            case 'ver':
                            {                                
                                this.modal=1;
                                this.tituloModal='Ver Respuesta';                                
                                this.respuesta_id=data['id'];
                                this.reference = data['reference'];
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
                                this.cc_type = data['cc_type'];
                                this.tp_operation = data['tp_operation'];
                                this.cc_name=data['cc_name'];
                                this.cc_number=data['cc_number'];
                                this.cc_expmonth=data['cc_expmonth'];
                                this.cc_expyear=data['cc_expyear'];
                                this.amount=data['amount'];
                                this.id_url=data['id_url'];
                                this.email=data['email'];
                                this.payment_type=data['payment_type'];
                                this.promocion=data['promocion'];
                                this.number_tkn=data['number_tkn'];
                                this.cc_mask=data['cc_mask'];
                                this.tipoAccion = 3;
                                window.scrollTo(0,0);
                                break;
                            }                                           
                        }
                    }
                }
            }            
        },
        watch: {
            tipo: {
                handler(val){
                    this.listarRespuesta(1,this.buscar,this.criterio);
                },
                deep: true
            }
        },
        mounted() {
            this.listarRespuesta(1,this.buscar,this.criterio);
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
